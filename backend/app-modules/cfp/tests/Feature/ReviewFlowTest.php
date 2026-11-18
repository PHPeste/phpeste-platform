<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => now()->subWeek(), 'closes_at' => now()->addWeek()]);
    $this->track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 2, 'position' => 1]);
    $this->relevance = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Relevância', 'weight' => 3, 'position' => 1]);
    $this->clarity = CfpCriterion::create(['edition_id' => $this->edition->id, 'name' => 'Clareza', 'weight' => 1, 'position' => 2]);

    $this->organizer = member($this->edition, EditionRole::Organizer, 'Rita Organizadora');
    $this->reviewers = [member($this->edition, EditionRole::Reviewer, 'Revisor A'), member($this->edition, EditionRole::Reviewer, 'Revisor B'), member($this->edition, EditionRole::Reviewer, 'Revisor C')];
    $this->proposals = [submitted($this->edition, $this->track, 1, 'PB'), submitted($this->edition, $this->track, 2, 'SP'), submitted($this->edition, $this->track, 3, 'CE')];
    $this->base = "/api/v1/editions/{$this->edition->id}/cfp";
});

function member(Edition $edition, EditionRole $role, string $name, ?string $company = null): User
{
    $user = User::factory()->create(['name' => $name]);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => $role]);

    if ($company !== null) {
        SpeakerProfile::create(['user_id' => $user->id, 'display_name' => $name, 'company' => $company, 'links' => []]);
    }

    return $user;
}

function submitted(Edition $edition, CfpTrack $track, int $number, string $state, ?User $author = null, ?string $company = null): Proposal
{
    $author ??= User::factory()->create(['name' => "Autora {$number}"]);
    SpeakerProfile::query()->updateOrCreate(['user_id' => $author->id], ['display_name' => $author->name, 'state' => $state, 'company' => $company, 'links' => []]);

    $proposal = Proposal::create([
        'edition_id' => $edition->id, 'number' => $number, 'track_id' => $track->id, 'title' => "Proposta {$number}",
        'summary' => 'Resumo', 'audience' => 'Público', 'format' => 'talk', 'level' => 'beginner', 'status' => 'submitted', 'submitted_at' => now(),
    ]);
    $proposal->speakers()->create(['edition_id' => $edition->id, 'user_id' => $author->id, 'is_owner' => true]);

    return $proposal->load('speakers');
}

function assign(object $test, Proposal $proposal, User $reviewer): TestResponse
{
    Sanctum::actingAs($test->organizer);

    return $test->postJson("{$test->base}/proposals/{$proposal->id}/assignments", ['reviewer_id' => $reviewer->id]);
}

function reviewAs(object $test, User $reviewer, Proposal $proposal, int $relevance, int $clarity, bool $submit = true): TestResponse
{
    Sanctum::actingAs($reviewer);

    return $test->putJson("{$test->base}/proposals/{$proposal->id}/review", [
        'scores' => [$test->relevance->id => $relevance, $test->clarity->id => $clarity],
        'comment' => 'Boa proposta.',
        'submit' => $submit,
    ]);
}

it('atribui revisor manualmente e recusa conflito de interesse', function (): void {
    assign($this, $this->proposals[0], $this->reviewers[0])->assertCreated();
    assign($this, $this->proposals[0], $this->reviewers[0])->assertCreated();

    $selfReviewer = member($this->edition, EditionRole::Reviewer, 'Revisora que enviou');
    $own = submitted($this->edition, $this->track, 4, 'PB', $selfReviewer);
    assign($this, $own, $selfReviewer)
        ->assertStatus(422)
        ->assertJsonPath('errors.reviewer_id.0', 'Quem avalia não pode ser autor nem coautor da proposta.');

    $colleague = member($this->edition, EditionRole::Reviewer, 'Colega', 'Acme Software');
    $fromAcme = submitted($this->edition, $this->track, 5, 'BA', null, 'ACME software ');
    assign($this, $fromAcme, $colleague)
        ->assertStatus(422)
        ->assertJsonPath('errors.reviewer_id.0', 'Quem avalia trabalha na mesma empresa de quem enviou a proposta.');

    $finance = member($this->edition, EditionRole::Finance, 'Financeiro');
    assign($this, $this->proposals[0], $finance)->assertStatus(422);
});

it('revisor não avalia a própria proposta nem proposta que não recebeu', function (): void {
    $selfReviewer = member($this->edition, EditionRole::Reviewer, 'Revisora que enviou');
    $own = submitted($this->edition, $this->track, 4, 'PB', $selfReviewer);

    reviewAs($this, $selfReviewer, $own, 5, 5)->assertForbidden();
    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 5)->assertForbidden();
});

it('distribui automaticamente equilibrando a carga e pulando conflitos', function (): void {
    $conflicted = member($this->edition, EditionRole::Reviewer, 'Revisora autora');
    $this->proposals[] = submitted($this->edition, $this->track, 4, 'PE', $conflicted);
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->base}/assignments/auto", ['reviewers_per_proposal' => 3])
        ->assertOk()
        ->assertJsonPath('data.created', 12)
        ->assertJsonPath('data.unfilled', []);

    $load = Proposal::query()->forEdition($this->edition->id)->with('assignments')->get()->flatMap->assignments->countBy('reviewer_id');
    expect($load->max() - $load->min())->toBeLessThanOrEqual(1)
        ->and($this->proposals[3]->assignments()->where('reviewer_id', $conflicted->id)->exists())->toBeFalse();

    $this->postJson("{$this->base}/assignments/auto", ['reviewers_per_proposal' => 3])->assertJsonPath('data.created', 0);
    $this->postJson("{$this->base}/assignments/auto", ['reviewers_per_proposal' => 5])->assertJsonPath('data.unfilled', [4]);
});

it('salva rascunho, exige todos os critérios para enviar e trava depois de enviar', function (): void {
    assign($this, $this->proposals[0], $this->reviewers[0]);
    Sanctum::actingAs($this->reviewers[0]);

    $this->putJson("{$this->base}/proposals/{$this->proposals[0]->id}/review", ['scores' => [$this->relevance->id => 4]])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');

    $this->putJson("{$this->base}/proposals/{$this->proposals[0]->id}/review", ['scores' => [$this->relevance->id => 4], 'submit' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['scores']);

    $this->putJson("{$this->base}/proposals/{$this->proposals[0]->id}/review", ['scores' => [$this->relevance->id => 6]])
        ->assertStatus(422);

    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 1)
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.weighted_score', 4);

    reviewAs($this, $this->reviewers[0], $this->proposals[0], 1, 1)->assertStatus(422)->assertJsonValidationErrors(['review']);
});

it('revisor não vê nota de outros revisores antes de enviar a sua', function (): void {
    foreach ($this->reviewers as $reviewer) {
        assign($this, $this->proposals[0], $reviewer);
    }

    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 5);
    reviewAs($this, $this->reviewers[1], $this->proposals[0], 2, 2, submit: false);

    $this->getJson("{$this->base}/proposals/{$this->proposals[0]->id}/reviews")->assertForbidden();
    $this->getJson("{$this->base}/proposals/{$this->proposals[0]->id}/review")->assertOk()->assertJsonPath('data.review.scores.'.$this->relevance->id, 2);

    reviewAs($this, $this->reviewers[1], $this->proposals[0], 2, 2);
    $this->getJson("{$this->base}/proposals/{$this->proposals[0]->id}/reviews")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.reviewer', 'Revisor 1')
        ->assertJsonPath('data.1.reviewer', 'Você');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->base}/proposals/{$this->proposals[0]->id}/reviews")->assertJsonPath('data.0.reviewer', 'Revisor A');
});

it('a fila do revisor traz pendentes primeiro e esconde a autoria', function (): void {
    foreach ($this->proposals as $proposal) {
        assign($this, $proposal, $this->reviewers[0]);
    }

    reviewAs($this, $this->reviewers[0], $this->proposals[0], 4, 4);
    reviewAs($this, $this->reviewers[0], $this->proposals[1], 3, 3, submit: false);

    $response = $this->getJson("{$this->base}/reviews/queue")
        ->assertOk()
        ->assertJsonPath('data.0.number', 3)
        ->assertJsonPath('data.0.my_review_status', 'pending')
        ->assertJsonPath('data.1.my_review_status', 'draft')
        ->assertJsonPath('data.2.my_review_status', 'submitted')
        ->assertJsonPath('data.0.speakers_hidden', true);

    expect($response->getContent())->not->toContain('Autora 1');
});

it('mostra o quadro de avaliação com nota ponderada, estados, empate e resumo', function (): void {
    foreach ($this->proposals as $proposal) {
        assign($this, $proposal, $this->reviewers[0]);
    }

    assign($this, $this->proposals[2], $this->reviewers[1]);

    reviewAs($this, $this->reviewers[0], $this->proposals[0], 4, 4);
    reviewAs($this, $this->reviewers[0], $this->proposals[1], 4, 4);
    reviewAs($this, $this->reviewers[0], $this->proposals[2], 5, 1);

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->base}/evaluation")
        ->assertOk()
        ->assertJsonPath('summary.proposals', 3)
        ->assertJsonPath('summary.reviewed', 2)
        ->assertJsonPath('summary.without_reviewer', 0)
        ->assertJsonPath('summary.average_score', 4)
        ->assertJsonPath('summary.nordeste_percent', 67)
        ->assertJsonPath('summary.nordeste_quota_percent', 50)
        ->assertJsonPath('data.0.review_status', 'tie')
        ->assertJsonPath('data.1.review_status', 'tie')
        ->assertJsonPath('data.2.review_status', 'pending')
        ->assertJsonPath('data.2.final_score', 4)
        ->assertJsonPath('data.0.speakers.0.name', 'Autora 1');

    $this->getJson("{$this->base}/evaluation?status=pending")->assertJsonCount(1, 'data');
});

it('a nota final recalcula quando um critério muda de peso, com confirmação', function (): void {
    assign($this, $this->proposals[0], $this->reviewers[0]);
    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 1);

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->base}/evaluation")->assertJsonPath('data.0.final_score', 4);

    $this->patchJson("{$this->base}/criteria/{$this->clarity->id}", ['weight' => 3])
        ->assertStatus(422)
        ->assertJsonPath('errors.confirm.0', fn (string $message): bool => str_starts_with($message, 'Já existem 1 avaliação enviada.'));

    $this->patchJson("{$this->base}/criteria/{$this->clarity->id}", ['weight' => 3, 'confirm' => true])->assertOk();
    $this->getJson("{$this->base}/evaluation")->assertJsonPath('data.0.final_score', 3);
});

it('exporta as notas em CSV, escapando fórmulas', function (): void {
    $this->proposals[0]->update(['title' => '=HYPERLINK("http://evil")']);
    assign($this, $this->proposals[0], $this->reviewers[0]);
    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 1);

    Sanctum::actingAs($this->organizer);
    $response = $this->get("{$this->base}/evaluation/export")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();

    expect($csv)->toContain('Número,Título,Trilha')
        ->toContain('Relevância (peso 3)')
        ->toContain("'=HYPERLINK")
        ->toContain('"4,00"')
        ->toContain('Autora 1');
});

it('não desfaz atribuição de quem já enviou nota', function (): void {
    assign($this, $this->proposals[0], $this->reviewers[0]);
    assign($this, $this->proposals[0], $this->reviewers[1]);
    reviewAs($this, $this->reviewers[0], $this->proposals[0], 5, 5);
    Sanctum::actingAs($this->organizer);

    $this->deleteJson("{$this->base}/proposals/{$this->proposals[0]->id}/assignments/{$this->reviewers[0]->id}")->assertStatus(422);
    $this->deleteJson("{$this->base}/proposals/{$this->proposals[0]->id}/assignments/{$this->reviewers[1]->id}")->assertNoContent();
    $this->getJson("{$this->base}/proposals/{$this->proposals[0]->id}/assignments")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Revisor A');
});

it('só a organização vê o quadro, exporta e atribui', function (): void {
    Sanctum::actingAs($this->reviewers[0]);

    $this->getJson("{$this->base}/evaluation")->assertForbidden();
    $this->get("{$this->base}/evaluation/export")->assertForbidden();
    $this->postJson("{$this->base}/assignments/auto", ['reviewers_per_proposal' => 2])->assertForbidden();
});
