<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Models\EditionDomain;
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
    EditionDomain::create(['edition_id' => $this->edition->id, 'host' => '2027.phpeste.dev', 'is_primary' => true, 'verification_token' => 'x', 'verified_at' => now()]);
    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01', 'closes_at' => '2027-04-15', 'confirmation_days' => 7]);
    $this->criterion = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Relevância', 'weight' => 1, 'position' => 1]);
    $this->track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 1, 'position' => 1]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->ana = User::factory()->create(['name' => 'Ana Martins', 'email' => 'ana@example.com']);
    $this->joao = User::factory()->create(['name' => 'João Paulo', 'email' => 'joao@example.com']);
    $this->first = proposalBy($this, $this->ana, 1, 5);
    $this->second = proposalBy($this, $this->joao, 2, 4);
    $this->admin = "/api/v1/editions/{$this->edition->id}/cfp";
    $this->speaker = "/api/v1/speaker/editions/{$this->edition->id}";
});

function proposalBy(object $test, User $author, int $number, int $score): Proposal
{
    SpeakerProfile::create(['user_id' => $author->id, 'display_name' => $author->name, 'state' => 'PB', 'links' => []]);
    $proposal = Proposal::create([
        'edition_id' => $test->edition->id, 'number' => $number, 'track_id' => $test->track->id, 'title' => "Palestra {$number}",
        'summary' => 'R', 'audience' => 'P', 'format' => 'talk', 'level' => 'beginner', 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $proposal->speakers()->create(['edition_id' => $test->edition->id, 'user_id' => $author->id, 'is_owner' => true]);
    $review = $proposal->reviews()->create(['edition_id' => $test->edition->id, 'reviewer_id' => $test->organizer->id, 'status' => Review::SUBMITTED, 'submitted_at' => now()]);
    $review->scores()->create(['criterion_id' => $test->criterion->id, 'score' => $score]);

    return $proposal;
}

function decideAs(object $test, array $decisions): void
{
    Sanctum::actingAs($test->organizer);
    $test->postJson("{$test->admin}/decision/apply", ['decisions' => array_map(
        fn (string $id, string $decision): array => ['proposal_id' => $id, 'decision' => $decision],
        array_keys($decisions),
        $decisions,
    )])->assertOk();
}

it('vai de aprovada a confirmada: convite com prazo e link para a área do palestrante', function (): void {
    decideAs($this, [$this->first->id => 'accepted', $this->second->id => 'waitlisted']);

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('ana@example.com')
        && $mail->email->subject === 'Sua proposta foi aprovada no PHPeste 2027'
        && str_contains($mail->email->html, 'até 8 de maio')
        && str_contains($mail->email->html, "https://2027.phpeste.dev/palestrante/propostas/{$this->first->id}"));
    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('joao@example.com') && str_contains($mail->email->subject, 'lista de espera'));

    $this->getJson("{$this->admin}/confirmations")
        ->assertOk()
        ->assertJsonPath('summary.pending', 1)
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.deadline_at', '2027-05-08T23:59:59Z')
        ->assertJsonPath('data.0.speakers.0.name', 'Ana Martins');

    Sanctum::actingAs($this->ana);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/confirmations")->assertJsonPath('summary.confirmed', 1)->assertJsonPath('summary.pending', 0);
    $this->assertDatabaseHas('audit_entries', ['action' => 'cfp.proposal_decided', 'subject_id' => $this->first->id]);
});

it('recusa libera a vaga e sugere a próxima da lista de espera, que só sobe com confirmação humana', function (): void {
    decideAs($this, [$this->first->id => 'accepted', $this->second->id => 'waitlisted']);

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/promotions")->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs($this->ana);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/decline", ['reason' => 'Não consigo viajar nessa data.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'declined');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/promotions")
        ->assertJsonPath('data.0.open_slots', 1)
        ->assertJsonPath('data.0.candidates.0.proposal_id', $this->second->id);

    expect($this->second->refresh()->decision?->value)->toBe('waitlisted');

    $this->postJson("{$this->admin}/promotions", ['proposal_id' => $this->first->id])->assertStatus(422);
    $this->postJson("{$this->admin}/promotions", ['proposal_id' => $this->second->id])
        ->assertOk()
        ->assertJsonPath('data.decision', 'accepted')
        ->assertJsonPath('data.confirmation_status', 'pending');

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('joao@example.com') && str_contains($mail->email->subject, 'aprovada'));
    $this->getJson("{$this->admin}/promotions")->assertJsonCount(0, 'data');
});

it('desistência depois de confirmar também libera a vaga', function (): void {
    decideAs($this, [$this->first->id => 'accepted', $this->second->id => 'waitlisted']);
    Sanctum::actingAs($this->ana);

    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/give-up")->assertStatus(422);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertOk();
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/give-up")->assertOk()->assertJsonPath('data.status', 'withdrawn');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/promotions")->assertJsonPath('data.0.candidates.0.proposal_id', $this->second->id);
});

it('lembra perto do prazo e marca sem resposta depois, sem recusar sozinho', function (): void {
    decideAs($this, [$this->first->id => 'accepted']);
    Mail::fake();

    $this->travelTo('2027-05-06 12:00:00');
    $this->artisan('cfp:confirmation-deadlines')->assertSuccessful();
    Mail::assertNothingSent();

    $this->travelTo('2027-05-07 08:00:00');
    $this->artisan('cfp:confirmation-deadlines');
    $this->artisan('cfp:confirmation-deadlines');
    Mail::assertSent(EditionMail::class, 1);
    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->email->subject === 'Confirme sua palestra no PHPeste 2027 até 8 de maio');

    $this->travelTo('2027-05-09 00:30:00');
    $this->artisan('cfp:confirmation-deadlines');

    $confirmation = SpeakerConfirmation::query()->forEdition($this->edition->id)->firstOrFail();
    expect($confirmation->status->value)->toBe('no_response')
        ->and($this->first->refresh()->decision?->value)->toBe('accepted');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/confirmations")->assertJsonPath('summary.no_response', 1)->assertJsonPath('summary.declined', 0);
    $this->getJson("{$this->admin}/promotions")->assertJsonCount(0, 'data');

    $this->postJson("{$this->admin}/confirmations/{$this->first->id}/extend", ['deadline_at' => '2027-05-12T23:59:00Z'])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    Sanctum::actingAs($this->ana);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertOk();
});

it('quem não respondeu no prazo ainda pode confirmar', function (): void {
    decideAs($this, [$this->first->id => 'accepted']);
    $this->travelTo('2027-05-10 10:00:00');
    $this->artisan('cfp:confirmation-deadlines');

    Sanctum::actingAs($this->ana);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
});

it('só quem criou a proposta responde pela participação', function (): void {
    $coauthor = User::factory()->create();
    $this->first->speakers()->create(['edition_id' => $this->edition->id, 'user_id' => $coauthor->id, 'is_owner' => false]);
    decideAs($this, [$this->first->id => 'accepted']);

    Sanctum::actingAs($coauthor);
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->postJson("{$this->speaker}/proposals/{$this->first->id}/confirm")->assertNotFound();
});

it('palestrante informa a logística e a organização vê também as restrições, que o palestrante não vê aqui', function (): void {
    decideAs($this, [$this->first->id => 'accepted']);
    SpeakerPrivateDetails::create(['user_id' => $this->ana->id, 'accessibility_needs' => 'Rampa no palco', 'dietary_restrictions' => 'Vegetariana']);

    Sanctum::actingAs($this->ana);
    $this->putJson("{$this->speaker}/logistics", ['origin_city' => 'Recife', 'origin_state' => 'PE', 'needs_travel' => true, 'needs_lodging' => true, 'travel_paid_by_organization' => true])
        ->assertOk()
        ->assertJsonPath('data.origin_state', 'PE')
        ->assertJsonPath('data.needs_lodging', true)
        ->assertJsonPath('data.travel_paid_by_organization', false)
        ->assertJsonMissingPath('data.restrictions')
        ->assertJsonMissingPath('data.organization_notes');

    Sanctum::actingAs($this->organizer);
    $this->patchJson("{$this->admin}/logistics/{$this->ana->id}", ['travel_paid_by_organization' => true, 'lodging_paid_by_organization' => true, 'organization_notes' => 'Hotel Tambaú, 2 noites'])
        ->assertOk()
        ->assertJsonPath('data.origin_city', 'Recife')
        ->assertJsonPath('data.travel_paid_by_organization', true)
        ->assertJsonPath('data.restrictions.accessibility_needs', 'Rampa no palco')
        ->assertJsonPath('data.organization_notes', 'Hotel Tambaú, 2 noites');
});

it('acessibilidade e logística só para a organização, e só de quem foi aprovado', function (): void {
    decideAs($this, [$this->first->id => 'accepted', $this->second->id => 'waitlisted']);
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);

    Sanctum::actingAs($reviewer);
    $this->getJson("{$this->admin}/logistics/{$this->ana->id}")->assertForbidden();
    $this->getJson("{$this->admin}/confirmations")->assertForbidden();

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/logistics/{$this->joao->id}")->assertNotFound();
});
