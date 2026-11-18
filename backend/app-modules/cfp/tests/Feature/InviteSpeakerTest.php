<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
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
use PHPeste\Notifications\Mail\EditionMail;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2027-05-01 10:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01', 'closes_at' => '2027-04-15', 'confirmation_days' => 10, 'proposals_per_person' => 1]);
    $this->criterion = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Relevância', 'weight' => 1, 'position' => 1]);
    $this->track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 2, 'position' => 1]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/cfp";
    Sanctum::actingAs($this->organizer);
});

function invite(object $test, array $overrides = []): TestResponse
{
    return $test->postJson("{$test->base}/speakers/invite", [
        'name' => 'Keynote Convidada', 'email' => 'Keynote@Exemplo.dev', 'title' => 'O futuro do PHP no Nordeste',
        'track_id' => $test->track->id, 'format' => 'talk', ...$overrides,
    ]);
}

function cfpProposal(object $test, int $number, int $score): Proposal
{
    $author = User::factory()->create();
    SpeakerProfile::create(['user_id' => $author->id, 'display_name' => $author->name, 'state' => 'PB', 'links' => []]);
    $proposal = Proposal::create(['edition_id' => $test->edition->id, 'number' => $number, 'track_id' => $test->track->id, 'title' => "CFP {$number}", 'summary' => 'R', 'audience' => 'P', 'format' => 'talk', 'level' => 'beginner', 'status' => 'submitted', 'submitted_at' => now()]);
    $proposal->speakers()->create(['edition_id' => $test->edition->id, 'user_id' => $author->id, 'is_owner' => true]);
    $review = $proposal->reviews()->create(['edition_id' => $test->edition->id, 'reviewer_id' => $test->organizer->id, 'status' => Review::SUBMITTED, 'submitted_at' => now()]);
    $review->scores()->create(['criterion_id' => $test->criterion->id, 'score' => $score]);

    return $proposal;
}

it('convida quem ainda não tem conta: cria a conta, a proposta aprovada, a confirmação e manda o convite', function (): void {
    $response = invite($this)->assertCreated()->assertJsonPath('data.number', 1)->assertJsonPath('data.deadline_at', '2027-05-11T23:59:59Z');

    $user = User::query()->where('email', 'keynote@exemplo.dev')->sole();
    expect($user->password)->toBeNull()->and($user->hasVerifiedEmail())->toBeTrue();

    $proposal = Proposal::query()->forEdition($this->edition->id)->findOrFail($response->json('data.proposal_id'));
    expect($proposal->origin->value)->toBe('invited')
        ->and($proposal->decision->value)->toBe('accepted')
        ->and($proposal->decided_by)->toBe($this->organizer->id);

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('keynote@exemplo.dev')
        && $mail->email->subject === 'Convite para palestrar no PHPeste 2027'
        && str_contains($mail->email->html, 'até 11 de maio')
        && str_contains($mail->email->html, 'Esqueci minha senha'));
    Mail::assertNotSent(EditionMail::class, fn (EditionMail $mail): bool => str_contains($mail->email->subject, 'aprovada'));

    $this->getJson("{$this->base}/speakers")
        ->assertJsonPath('data.0.name', 'Keynote Convidada')
        ->assertJsonPath('data.0.status', 'pending')
        ->assertJsonPath('data.0.proposal.origin', 'invited')
        ->assertJsonPath('summary.awaiting', 1);

    $log = AuditLog::query()->where('action', 'cfp.speaker_invited')->sole();
    expect($log->actor_id)->toBe($this->organizer->id);
});

it('usa a conta que já existe e recusa quem já tem palestra aprovada', function (): void {
    $existing = User::factory()->create(['email' => 'keynote@exemplo.dev', 'name' => 'Já Cadastrada']);

    invite($this)->assertCreated()->assertJsonPath('data.user_id', $existing->id);
    expect(User::query()->where('email', 'keynote@exemplo.dev')->count())->toBe(1);

    invite($this, ['title' => 'Outra'])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Essa pessoa já tem palestra aprovada nesta edição.');
});

it('a palestra convidada fica fora da avaliação e ocupa vaga da trilha', function (): void {
    cfpProposal($this, 1, 5);
    cfpProposal($this, 2, 4);
    invite($this)->assertCreated();

    $this->getJson("{$this->base}/evaluation")->assertJsonCount(2, 'data');
    $this->getJson("{$this->base}/tracks")->assertJsonPath('data.0.proposals_count', 2);

    $ranking = $this->getJson("{$this->base}/decision/ranking")->assertOk()->json('data');
    expect($ranking['tracks'][0]['track']['slots'])->toBe(1)
        ->and($ranking['tracks'][0]['track']['reserved'])->toBe(1)
        ->and(collect($ranking['tracks'][0]['proposals'])->pluck('suggested_decision')->all())->toBe(['accepted', 'waitlisted'])
        ->and(collect($ranking['outcome']['tracks'])->firstWhere('name', 'Backend')['accepted'])->toBe(1);

    $invited = Proposal::query()->forEdition($this->edition->id)->where('origin', 'invited')->sole();
    $this->postJson("{$this->base}/decision/apply", ['decisions' => [['proposal_id' => $invited->id, 'decision' => 'rejected']]])->assertStatus(422);
    $this->postJson("{$this->base}/proposals/{$invited->id}/assignments", ['reviewer_id' => $this->organizer->id])->assertNotFound();
});

it('valida trilha da edição, formato e e-mail', function (): void {
    $other = CfpTrack::create(['edition_id' => Edition::factory()->create()->id, 'name' => 'Outra', 'slots' => 1, 'position' => 1]);

    invite($this, ['email' => 'não-é-email', 'track_id' => $other->id, 'format' => 'show'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'track_id', 'format']);
});

it('só a organização convida', function (): void {
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);

    invite($this)->assertForbidden();
});
