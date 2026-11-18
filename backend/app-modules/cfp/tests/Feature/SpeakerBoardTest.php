<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerPrivateDetails;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Mail\EditionMail;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2027-05-01 10:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01', 'closes_at' => '2027-04-15', 'confirmation_days' => 7, 'nordeste_quota_percent' => 50]);
    $this->criterion = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Relevância', 'weight' => 1, 'position' => 1]);
    $this->track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 2, 'position' => 1]);
    CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Carreira', 'slots' => 3, 'position' => 2]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->ana = boardSpeaker($this, 'Ana Martins', 'PB', 1, 5);
    $this->carlos = boardSpeaker($this, 'Carlos Lima', 'PE', 2, 4);
    $this->rafael = boardSpeaker($this, 'Rafael Freitas', 'SP', 3, 3);
    $this->base = "/api/v1/editions/{$this->edition->id}/cfp";

    Sanctum::actingAs($this->organizer);
    $this->postJson("{$this->base}/decision/apply", ['decisions' => [
        ['proposal_id' => $this->ana['proposal']->id, 'decision' => 'accepted'],
        ['proposal_id' => $this->carlos['proposal']->id, 'decision' => 'accepted'],
        ['proposal_id' => $this->rafael['proposal']->id, 'decision' => 'waitlisted'],
    ]])->assertOk();
    SpeakerConfirmation::query()->forEdition($this->edition->id)->where('proposal_id', $this->ana['proposal']->id)->update(['status' => 'confirmed', 'responded_at' => now()]);
});

/**
 * @return array{user: User, proposal: Proposal}
 */
function boardSpeaker(object $test, string $name, string $state, int $number, int $score): array
{
    $user = User::factory()->create(['name' => $name, 'email' => mb_strtolower(explode(' ', $name)[0]).'@example.com']);
    SpeakerProfile::create(['user_id' => $user->id, 'display_name' => $name, 'state' => $state, 'job_title' => 'Engenheira', 'bio' => "Bio de {$name}", 'links' => []]);
    $proposal = Proposal::create([
        'edition_id' => $test->edition->id, 'number' => $number, 'track_id' => $test->track->id, 'title' => "Palestra {$number}",
        'summary' => 'R', 'audience' => 'P', 'format' => 'talk', 'level' => 'beginner', 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $proposal->speakers()->create(['edition_id' => $test->edition->id, 'user_id' => $user->id, 'is_owner' => true]);
    $review = $proposal->reviews()->create(['edition_id' => $test->edition->id, 'reviewer_id' => $test->organizer->id, 'status' => Review::SUBMITTED, 'submitted_at' => now()]);
    $review->scores()->create(['criterion_id' => $test->criterion->id, 'score' => $score]);

    return ['user' => $user, 'proposal' => $proposal];
}

it('lista quem foi aprovado ou está na lista de espera, com indicadores e cobertura dos nove estados', function (): void {
    $response = $this->getJson("{$this->base}/speakers")->assertOk();

    $response->assertJsonPath('summary', ['confirmed' => 1, 'awaiting' => 1, 'waitlisted' => 1, 'slots' => 5, 'nordeste_percent' => 100, 'nordeste_quota_percent' => 50])
        ->assertJsonCount(9, 'coverage')
        ->assertJsonPath('coverage.0', ['state' => 'AL', 'confirmed' => 0])
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Ana Martins')
        ->assertJsonPath('data.0.state', 'PB')
        ->assertJsonPath('data.0.nordeste', true)
        ->assertJsonPath('data.0.status', 'confirmed')
        ->assertJsonPath('data.1.status', 'pending')
        ->assertJsonPath('data.1.status_label', 'Aguardando resposta')
        ->assertJsonPath('data.2.status', 'waitlisted')
        ->assertJsonPath('data.2.nordeste', false);

    expect(collect($response->json('coverage'))->firstWhere('state', 'PB'))->toBe(['state' => 'PB', 'confirmed' => 1])
        ->and(collect($response->json('coverage'))->firstWhere('state', 'PE'))->toBe(['state' => 'PE', 'confirmed' => 0]);
});

it('mostra o detalhe da pessoa e esconde acessibilidade e alimentação de quem não tem permissão', function (): void {
    SpeakerPrivateDetails::create(['user_id' => $this->ana['user']->id, 'accessibility_needs' => 'Rampa no palco', 'dietary_restrictions' => 'Vegetariana']);

    $this->getJson("{$this->base}/speakers/{$this->ana['user']->id}")
        ->assertOk()
        ->assertJsonPath('data.profile.bio', 'Bio de Ana Martins')
        ->assertJsonPath('data.proposals.0.status', 'confirmed')
        ->assertJsonPath('data.can_view_private_data', true)
        ->assertJsonPath('data.logistics.restrictions.accessibility_needs', 'Rampa no palco');

    Gate::define('speakers.private', fn (): bool => false);

    $this->getJson("{$this->base}/speakers/{$this->ana['user']->id}")
        ->assertJsonPath('data.can_view_private_data', false)
        ->assertJsonPath('data.logistics.restrictions', null);
    $this->getJson("{$this->base}/logistics/{$this->ana['user']->id}")->assertJsonPath('data.restrictions', null);

    $this->getJson("{$this->base}/speakers/{$this->rafael['user']->id}")->assertOk()->assertJsonPath('data.logistics', null);
    $this->getJson("{$this->base}/speakers/{$this->organizer->id}")->assertNotFound();
});

it('lembra quem está aguardando resposta, sem repetir o lembrete em menos de 12 horas', function (): void {
    Mail::fake();

    $this->postJson("{$this->base}/speakers/remind")->assertOk()->assertJsonPath('data', ['reminded' => 1, 'skipped' => 0]);
    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('carlos@example.com'));
    Mail::assertNotSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('ana@example.com'));

    $this->postJson("{$this->base}/speakers/remind", ['proposal_id' => $this->carlos['proposal']->id])->assertJsonPath('data', ['reminded' => 0, 'skipped' => 1]);

    $this->travel(13)->hours();
    $this->postJson("{$this->base}/speakers/remind", ['proposal_id' => $this->carlos['proposal']->id])->assertJsonPath('data', ['reminded' => 1, 'skipped' => 0]);
});

it('marca e desmarca presença só de quem tem palestra confirmada', function (): void {
    $this->postJson("{$this->base}/speakers/{$this->ana['user']->id}/attendance", ['present' => true])->assertOk()->assertJsonPath('data.updated', 1);
    $this->getJson("{$this->base}/speakers")->assertJsonPath('data.0.attended_at', '2027-05-01T10:00:00Z');

    $this->postJson("{$this->base}/speakers/{$this->ana['user']->id}/attendance", ['present' => false])->assertOk();
    $this->getJson("{$this->base}/speakers")->assertJsonPath('data.0.attended_at', null);

    $this->postJson("{$this->base}/speakers/{$this->carlos['user']->id}/attendance", ['present' => true])
        ->assertStatus(422)
        ->assertJsonPath('errors.present.0', 'Só dá para marcar presença de quem tem palestra confirmada.');
});

it('só a organização vê o quadro de palestrantes', function (): void {
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);

    $this->getJson("{$this->base}/speakers")->assertForbidden();
    $this->getJson("{$this->base}/speakers/{$this->ana['user']->id}")->assertForbidden();
    $this->postJson("{$this->base}/speakers/remind")->assertForbidden();
});
