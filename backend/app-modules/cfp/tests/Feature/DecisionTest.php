<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Events\ProposalDecided;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => now()->subMonth(), 'closes_at' => now()->subDay(), 'nordeste_quota_percent' => 50]);
    $this->criterion = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Relevância', 'weight' => 1, 'position' => 1]);
    $this->backend = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 2, 'position' => 1]);
    $this->carreira = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Carreira', 'slots' => 1, 'position' => 2]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->reviewer = User::factory()->create();

    $this->p = [
        1 => scored($this, $this->backend, 1, 'SP', 5),
        2 => scored($this, $this->backend, 2, 'PB', 4),
        3 => scored($this, $this->backend, 3, 'CE', 3),
        4 => scored($this, $this->carreira, 4, 'RJ', 4),
        5 => scored($this, $this->carreira, 5, 'BA', 4),
    ];
    $this->base = "/api/v1/editions/{$this->edition->id}/cfp/decision";
    Sanctum::actingAs($this->organizer);
});

function scored(object $test, CfpTrack $track, int $number, string $state, int $score): Proposal
{
    $author = User::factory()->create();
    SpeakerProfile::create(['user_id' => $author->id, 'display_name' => "Autor {$number}", 'state' => $state, 'links' => []]);
    $proposal = Proposal::create([
        'edition_id' => $test->edition->id, 'number' => $number, 'track_id' => $track->id, 'title' => "Proposta {$number}",
        'summary' => 'R', 'audience' => 'P', 'format' => 'talk', 'level' => 'beginner', 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $proposal->speakers()->create(['edition_id' => $test->edition->id, 'user_id' => $author->id, 'is_owner' => true]);
    $proposal->assignments()->create(['edition_id' => $test->edition->id, 'reviewer_id' => $test->reviewer->id]);
    $review = $proposal->reviews()->create(['edition_id' => $test->edition->id, 'reviewer_id' => $test->reviewer->id, 'status' => Review::SUBMITTED, 'submitted_at' => now()]);
    $review->scores()->create(['criterion_id' => $test->criterion->id, 'score' => $score]);

    return $proposal;
}

function decide(object $test, array $decisions): array
{
    return array_map(fn (int $number, ?string $decision): array => ['proposal_id' => $test->p[$number]->id, 'decision' => $decision], array_keys($decisions), $decisions);
}

it('ranqueia por trilha e nota, sugerindo aprovar até as vagas', function (): void {
    $this->getJson("{$this->base}/ranking")
        ->assertOk()
        ->assertJsonPath('data.tracks.0.track.name', 'Backend')
        ->assertJsonPath('data.tracks.0.proposals.0.number', 1)
        ->assertJsonPath('data.tracks.0.proposals.0.suggested_decision', 'accepted')
        ->assertJsonPath('data.tracks.0.proposals.1.suggested_decision', 'accepted')
        ->assertJsonPath('data.tracks.0.proposals.2.suggested_decision', 'waitlisted')
        ->assertJsonPath('data.tracks.0.proposals.2.rank', 3);
});

it('não decide sozinho quando há empate exatamente no corte de vagas', function (): void {
    $this->getJson("{$this->base}/ranking")
        ->assertJsonPath('data.tracks.1.track.name', 'Carreira')
        ->assertJsonPath('data.tracks.1.proposals.0.tie_at_cutoff', true)
        ->assertJsonPath('data.tracks.1.proposals.1.tie_at_cutoff', true)
        ->assertJsonPath('data.tracks.1.proposals.0.suggested_decision', 'waitlisted')
        ->assertJsonPath('data.tracks.0.proposals.0.tie_at_cutoff', false);
});

it('simula a cobertura e os alertas sem alterar nada', function (): void {
    Event::fake([ProposalDecided::class]);

    $this->postJson("{$this->base}/simulate", ['decisions' => decide($this, [1 => 'accepted', 2 => 'accepted', 3 => 'waitlisted', 4 => 'accepted', 5 => 'rejected'])])
        ->assertOk()
        ->assertJsonPath('data.decisions', ['accepted' => 3, 'waitlisted' => 1, 'rejected' => 1, 'undecided' => 0])
        ->assertJsonPath('data.coverage.by_state', ['PB' => 1, 'RJ' => 1, 'SP' => 1])
        ->assertJsonPath('data.coverage.nordeste_percent', 33)
        ->assertJsonPath('data.coverage.nordeste_states_missing', ['AL', 'BA', 'CE', 'MA', 'PE', 'PI', 'RN', 'SE'])
        ->assertJsonPath('data.tracks.0.accepted', 2)
        ->assertJsonPath('data.alerts.0', ['type' => 'nordeste_quota', 'message' => 'Palestrantes do Nordeste: 33%, abaixo da meta de 50%.'])
        ->assertJsonPath('data.alerts.1.type', 'states_missing');

    expect(Proposal::query()->forEdition($this->edition->id)->whereNotNull('decision')->count())->toBe(0);
    $this->assertDatabaseCount('audit_entries', 0);
    Event::assertNotDispatched(ProposalDecided::class);
});

it('avisa quando uma trilha passa das vagas e some o alerta da meta quando ela é atingida', function (): void {
    $this->postJson("{$this->base}/simulate", ['decisions' => decide($this, [2 => 'accepted', 3 => 'accepted', 5 => 'accepted', 4 => 'accepted'])])
        ->assertJsonPath('data.coverage.nordeste_percent', 75)
        ->assertJsonPath('data.alerts', fn (array $alerts): bool => collect($alerts)->pluck('type')->all() === ['states_missing', 'track_over_slots']);
});

it('aplica as decisões em transação, dispara ProposalDecided e registra na auditoria', function (): void {
    Event::fake([ProposalDecided::class]);

    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'accepted', 2 => 'accepted', 3 => 'waitlisted', 4 => 'accepted', 5 => 'waitlisted'])])
        ->assertOk()
        ->assertJsonPath('data.changed', 5)
        ->assertJsonPath('data.outcome.decisions.accepted', 3);

    expect($this->p[3]->refresh()->decision?->value)->toBe('waitlisted')
        ->and($this->p[1]->refresh()->decided_by)->toBe($this->organizer->id);
    Event::assertDispatchedTimes(ProposalDecided::class, 5);
    Event::assertDispatched(ProposalDecided::class, fn (ProposalDecided $event): bool => $event->proposalId === $this->p[3]->id && $event->decision->value === 'waitlisted' && !$event->previous instanceof ProposalDecision);
});

it('grava a trilha de auditoria de cada decisão', function (): void {
    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'accepted'])])->assertOk();
    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'waitlisted'])])->assertOk();
    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'waitlisted'])])->assertJsonPath('data.changed', 0);

    $this->assertDatabaseCount('audit_entries', 2);
    $this->assertDatabaseHas('audit_entries', ['action' => 'cfp.proposal_decided', 'subject_id' => $this->p[1]->id, 'actor_id' => $this->organizer->id]);
});

it('não aplica nada se alguma proposta for inválida ou sem decisão', function (): void {
    $other = Edition::factory()->create();
    $foreign = Proposal::create(['edition_id' => $other->id, 'number' => 1, 'title' => 'De outra edição', 'status' => 'submitted']);

    $this->postJson("{$this->base}/apply", ['decisions' => [...decide($this, [1 => 'accepted']), ['proposal_id' => $foreign->id, 'decision' => 'accepted']]])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['decisions.1.proposal_id']);

    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'accepted', 2 => null])])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['decisions']);

    expect(Proposal::query()->forEdition($this->edition->id)->whereNotNull('decision')->count())->toBe(0)
        ->and($foreign->refresh()->decision)->toBeNull();
});

it('só a organização decide', function (): void {
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($this->reviewer);

    $this->getJson("{$this->base}/ranking")->assertForbidden();
    $this->postJson("{$this->base}/simulate", ['decisions' => []])->assertForbidden();
    $this->postJson("{$this->base}/apply", ['decisions' => decide($this, [1 => 'accepted'])])->assertForbidden();
});
