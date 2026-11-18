<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/cfp";
    Sanctum::actingAs($this->organizer);
});

function withSubmittedReviews(int $count): void
{
    app()->instance(ReviewLedger::class, new readonly class($count) implements ReviewLedger
    {
        public function __construct(private int $count) {}

        public function submittedReviewsCount(string $editionId): int
        {
            return $this->count;
        }
    });
}

it('cria, lista em ordem, edita e remove trilhas', function (): void {
    $backend = $this->postJson("{$this->base}/tracks", ['name' => 'Backend e arquitetura', 'slots' => 6])
        ->assertCreated()
        ->assertJsonPath('data.position', 1)
        ->json('data.id');
    $this->postJson("{$this->base}/tracks", ['name' => 'Qualidade e testes', 'slots' => 4])->assertCreated()->assertJsonPath('data.position', 2);

    $this->postJson("{$this->base}/tracks", ['name' => 'Backend e arquitetura', 'slots' => 1])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    $this->patchJson("{$this->base}/tracks/{$backend}", ['slots' => 8])->assertOk()->assertJsonPath('data.slots', 8);
    $this->getJson("{$this->base}/tracks")->assertJsonPath('data.0.name', 'Backend e arquitetura')->assertJsonCount(2, 'data');

    $this->deleteJson("{$this->base}/tracks/{$backend}")->assertNoContent();
    $this->getJson("{$this->base}/tracks")->assertJsonCount(1, 'data');
});

it('mesma trilha pode existir em outra edição, e uma edição não mexe na trilha da outra', function (): void {
    $id = $this->postJson("{$this->base}/tracks", ['name' => 'Backend', 'slots' => 6])->json('data.id');

    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->postJson("/api/v1/editions/{$other->id}/cfp/tracks", ['name' => 'Backend', 'slots' => 2])->assertCreated();
    $this->patchJson("/api/v1/editions/{$other->id}/cfp/tracks/{$id}", ['slots' => 0])->assertNotFound();
});

it('cria e edita critérios livremente enquanto não há avaliações', function (): void {
    $id = $this->postJson("{$this->base}/criteria", ['name' => 'Relevância para a comunidade', 'description' => 'O tema interessa a quem vem ao evento?', 'weight' => 3])
        ->assertCreated()
        ->json('data.id');

    $this->patchJson("{$this->base}/criteria/{$id}", ['weight' => 2])->assertOk()->assertJsonPath('data.weight', 2);
    $this->postJson("{$this->base}/criteria", ['name' => 'Peso fora', 'weight' => 6])->assertStatus(422)->assertJsonValidationErrors(['weight']);
});

it('pede confirmação para mudar peso, criar ou remover critério depois que há avaliações', function (): void {
    $id = $this->postJson("{$this->base}/criteria", ['name' => 'Clareza', 'weight' => 2])->json('data.id');
    withSubmittedReviews(3);

    $this->patchJson("{$this->base}/criteria/{$id}", ['weight' => 4])
        ->assertStatus(422)
        ->assertJsonPath('errors.confirm.0', 'Já existem 3 avaliações enviadas. Mudar os critérios recalcula a nota final de todas as propostas; as notas dadas são preservadas. Envie confirm=true para continuar.');
    $this->postJson("{$this->base}/criteria", ['name' => 'Originalidade', 'weight' => 2])->assertStatus(422);
    $this->deleteJson("{$this->base}/criteria/{$id}")->assertStatus(422);

    $this->patchJson("{$this->base}/criteria/{$id}", ['name' => 'Clareza da proposta'])->assertOk();
    $this->patchJson("{$this->base}/criteria/{$id}", ['weight' => 4, 'confirm' => true])->assertOk()->assertJsonPath('data.weight', 4);
});

it('remove critério sem apagar a linha, para preservar as notas já dadas', function (): void {
    $id = $this->postJson("{$this->base}/criteria", ['name' => 'Clareza', 'weight' => 2])->json('data.id');
    withSubmittedReviews(1);

    $this->deleteJson("{$this->base}/criteria/{$id}", ['confirm' => true])->assertNoContent();

    $this->getJson("{$this->base}/criteria")->assertJsonCount(0, 'data');
    expect(CfpCriterion::query()->withTrashed()->forEdition($this->edition->id)->find($id)?->trashed())->toBeTrue();
});

it('conta as propostas enviadas por trilha, sem rascunho nem retirada', function (): void {
    $track = $this->postJson("{$this->base}/tracks", ['name' => 'Backend', 'slots' => 6])->assertJsonPath('data.proposals_count', 0)->json('data.id');

    foreach ([ProposalStatus::Submitted, ProposalStatus::Submitted, ProposalStatus::Draft, ProposalStatus::Withdrawn] as $number => $status) {
        Proposal::create(['edition_id' => $this->edition->id, 'number' => $number + 1, 'title' => "Proposta {$number}", 'track_id' => $track, 'status' => $status]);
    }

    $this->getJson("{$this->base}/tracks")->assertJsonPath('data.0.proposals_count', 2);
    $this->patchJson("{$this->base}/tracks/{$track}", ['slots' => 7])->assertJsonPath('data.proposals_count', 2);
});

it('diz quantas avaliações já existem junto com os critérios', function (): void {
    $this->getJson("{$this->base}/criteria")->assertJsonPath('meta.submitted_reviews', 0);

    withSubmittedReviews(12);

    $this->getJson("{$this->base}/criteria")->assertJsonPath('meta.submitted_reviews', 12);
});
