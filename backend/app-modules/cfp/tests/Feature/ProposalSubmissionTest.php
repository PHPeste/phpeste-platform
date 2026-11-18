<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Events\ProposalSubmitted;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Mail\EditionMail;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2027-03-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create(['name' => 'PHPeste 2027']);
    $this->settings = CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01 00:00:00', 'closes_at' => '2027-04-15 00:00:00', 'proposals_per_person' => 2]);
    $this->track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend e arquitetura', 'slots' => 6, 'position' => 1]);
    $this->speaker = speakerWithProfile('Ana Martins', 'PB');
    $this->url = "/api/v1/speaker/editions/{$this->edition->id}/proposals";
    Sanctum::actingAs($this->speaker);
});

function speakerWithProfile(string $name, string $state): User
{
    $user = User::factory()->create(['name' => $name]);
    SpeakerProfile::create(['user_id' => $user->id, 'display_name' => $name, 'state' => $state, 'links' => []]);

    return $user;
}

function completeProposal(object $test, array $overrides = []): string
{
    return $test->postJson($test->url, [
        'title' => 'Octane em produção: o que aprendemos',
        'track_id' => $test->track->id,
        'summary' => 'Relato de seis meses rodando um monolito Laravel com Octane e Swoole.',
        'audience' => 'Quem já usa Laravel e quer ganhar desempenho.',
        'format' => 'talk',
        'level' => 'intermediate',
        ...$overrides,
    ])->assertCreated()->json('data.id');
}

it('salva rascunho, edita e lista as próprias propostas com número sequencial', function (): void {
    $first = $this->postJson($this->url, ['title' => 'Rascunho de ideia'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.missing_for_submission', ['track_id', 'summary', 'audience', 'format', 'level'])
        ->assertJsonPath('data.speakers.0.is_owner', true)
        ->json('data.id');

    $this->patchJson("{$this->url}/{$first}", ['format' => 'workshop', 'track_id' => $this->track->id])
        ->assertOk()
        ->assertJsonPath('data.format_label', 'Oficina de 2 h')
        ->assertJsonPath('data.duration_minutes', 120)
        ->assertJsonPath('data.track.name', 'Backend e arquitetura');

    $this->postJson($this->url, ['title' => 'Outra'])->assertJsonPath('data.number', 2);
    $this->getJson($this->url)->assertOk()->assertJsonCount(2, 'data');
});

it('envia a proposta, confirma por e-mail e dispara ProposalSubmitted', function (): void {
    Event::fake([ProposalSubmitted::class]);
    $id = completeProposal($this);

    $this->postJson("{$this->url}/{$id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.submitted_at', '2027-03-10T12:00:00Z');

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo($this->speaker->email)
        && $mail->email->subject === 'Recebemos sua proposta para o PHPeste 2027'
        && str_contains($mail->email->html, 'Octane em produção: o que aprendemos'));
    Event::assertDispatched(ProposalSubmitted::class, fn (ProposalSubmitted $event): bool => $event->proposalId === $id);
});

it('rejeita envio fora da janela', function (string $moment): void {
    $id = completeProposal($this);
    $this->travelTo($moment);

    $this->postJson("{$this->url}/{$id}/submit")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cfp']);
})->with(['depois do fechamento' => '2027-04-15 00:00:01']);

it('rejeita envio antes da abertura', function (): void {
    $this->settings->update(['opens_at' => '2027-03-20 00:00:00']);

    $id = $this->postJson($this->url, ['title' => 'Antecipada'])->assertCreated()->json('data.id');

    $this->postJson("{$this->url}/{$id}/submit")->assertStatus(422)->assertJsonPath('errors.cfp.0', 'O CFP desta edição não está aberto para envio.');
});

it('exige todos os campos e o perfil de palestrante para enviar', function (): void {
    $draft = $this->postJson($this->url, ['title' => 'Incompleta'])->json('data.id');

    $this->postJson("{$this->url}/{$draft}/submit")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['track_id', 'summary', 'audience', 'format', 'level']);

    $semPerfil = User::factory()->create();
    Sanctum::actingAs($semPerfil);
    $id = completeProposal($this);

    $this->postJson("{$this->url}/{$id}/submit")->assertStatus(422)->assertJsonValidationErrors(['speaker_profile']);
});

it('respeita o limite de propostas por pessoa, e retirar libera a vaga', function (): void {
    $ids = [completeProposal($this), completeProposal($this), completeProposal($this)];

    $this->postJson("{$this->url}/{$ids[0]}/submit")->assertOk();
    $this->postJson("{$this->url}/{$ids[1]}/submit")->assertOk();
    $this->postJson("{$this->url}/{$ids[2]}/submit")
        ->assertStatus(422)
        ->assertJsonPath('errors.limit.0', 'Você já enviou 2 propostas, o limite desta edição.');

    $this->postJson("{$this->url}/{$ids[0]}/withdraw")->assertOk()->assertJsonPath('data.status', 'withdrawn');
    $this->postJson("{$this->url}/{$ids[2]}/submit")->assertOk();
    $this->patchJson("{$this->url}/{$ids[0]}", ['title' => 'Volta'])->assertStatus(422);
});

it('só a organização edita depois do fechamento', function (): void {
    $id = completeProposal($this);
    $this->postJson("{$this->url}/{$id}/submit");
    $this->travelTo('2027-04-20 10:00:00');

    $this->patchJson("{$this->url}/{$id}", ['title' => 'Mudança tardia'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cfp']);

    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);

    $this->patchJson("/api/v1/editions/{$this->edition->id}/cfp/proposals/{$id}", ['title' => 'Título revisado pela organização'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Título revisado pela organização');

    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);
    $this->patchJson("/api/v1/editions/{$this->edition->id}/cfp/proposals/{$id}", ['title' => 'x'])->assertForbidden();
});

it('coautor edita enquanto o CFP está aberto, mas só quem criou envia, retira e mexe nos coautores', function (): void {
    $coauthor = speakerWithProfile('João Paulo', 'CE');
    $id = completeProposal($this);

    $this->postJson("{$this->url}/{$id}/coauthors", ['email' => mb_strtoupper($coauthor->email)])
        ->assertOk()
        ->assertJsonCount(2, 'data.speakers');
    $this->postJson("{$this->url}/{$id}/coauthors", ['email' => $coauthor->email])->assertStatus(422);
    $this->postJson("{$this->url}/{$id}/coauthors", ['email' => 'ninguem@example.com'])->assertStatus(422);

    Sanctum::actingAs($coauthor);
    $this->getJson($this->url)->assertJsonCount(1, 'data');
    $this->patchJson("{$this->url}/{$id}", ['audience' => 'Times que já usam Laravel.'])->assertOk();
    $this->postJson("{$this->url}/{$id}/submit")->assertForbidden();
    $this->postJson("{$this->url}/{$id}/withdraw")->assertForbidden();

    Sanctum::actingAs($this->speaker);
    $this->deleteJson("{$this->url}/{$id}/coauthors/{$coauthor->id}")->assertOk()->assertJsonCount(1, 'data.speakers');
});

it('limita a três coautores', function (): void {
    $id = completeProposal($this);

    foreach (range(1, 3) as $i) {
        $this->postJson("{$this->url}/{$id}/coauthors", ['email' => User::factory()->create()->email])->assertOk();
    }

    $this->postJson("{$this->url}/{$id}/coauthors", ['email' => User::factory()->create()->email])
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Uma proposta pode ter até 3 coautores.');
});

it('ninguém vê a proposta de outra pessoa pela área do palestrante', function (): void {
    $id = completeProposal($this);
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("{$this->url}/{$id}")->assertNotFound();
    $this->patchJson("{$this->url}/{$id}", ['title' => 'x'])->assertNotFound();
    $this->getJson($this->url)->assertJsonCount(0, 'data');
});

it('não abre a área do palestrante para edição em rascunho', function (): void {
    $draft = Edition::factory()->create();

    $this->getJson("/api/v1/speaker/editions/{$draft->id}/proposals")->assertNotFound();
});

it('quem envia proposta passa a participar da edição para a organização ver o perfil', function (): void {
    $id = completeProposal($this);
    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    $profileUrl = "/api/v1/editions/{$this->edition->id}/people/{$this->speaker->id}/speaker-profile";

    Sanctum::actingAs($organizer);
    $this->getJson($profileUrl)->assertNotFound();

    Sanctum::actingAs($this->speaker);
    $this->postJson("{$this->url}/{$id}/submit");

    Sanctum::actingAs($organizer);
    $this->getJson($profileUrl)->assertOk()->assertJsonPath('data.public.display_name', 'Ana Martins');
});
