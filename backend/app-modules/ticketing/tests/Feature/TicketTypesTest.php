<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Models\User;
use PHPeste\Ticketing\Actions\AllocateTickets;
use PHPeste\Ticketing\Actions\ReleaseTickets;
use PHPeste\Ticketing\Cloning\TicketingCloner;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/ticketing";
    Sanctum::actingAs($this->organizer);
});

function createType(object $test, array $overrides = []): string
{
    return $test->postJson("{$test->base}/ticket-types", ['name' => 'Geral', 'description' => 'Dois dias de evento', ...$overrides])->assertCreated()->json('data.id');
}

function createBatch(object $test, string $typeId, array $overrides = []): string
{
    return $test->postJson("{$test->base}/ticket-types/{$typeId}/batches", ['name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 150, ...$overrides])->assertCreated()->json('data.id');
}

it('cria tipo com perguntas do checkout e lotes, e lista com status, estoque e lote atual', function (): void {
    $type = createType($this, ['questions' => [
        ['label' => 'Nome no crachá', 'type' => 'text', 'required' => true],
        ['label' => 'Tamanho de camiseta', 'type' => 'select', 'options' => ['P', 'M', 'G']],
        ['label' => 'Nome no crachá', 'type' => 'text'],
    ]]);
    $first = createBatch($this, $type, ['starts_at' => '2027-06-01T00:00:00Z', 'ends_at' => '2027-06-30T23:59:59Z']);
    createBatch($this, $type, ['name' => '2º lote', 'price_cents' => 20_000, 'starts_at' => '2027-07-01T00:00:00Z']);

    $this->getJson("{$this->base}/ticket-types")
        ->assertOk()
        ->assertJsonPath('data.0.questions.0', ['id' => 'nome_no_cracha', 'label' => 'Nome no crachá', 'type' => 'text', 'required' => true, 'options' => []])
        ->assertJsonPath('data.0.questions.1.options', ['P', 'M', 'G'])
        ->assertJsonPath('data.0.questions.2.id', 'nome_no_cracha_2')
        ->assertJsonPath('data.0.current_batch_id', $first)
        ->assertJsonPath('data.0.quantity', 300)
        ->assertJsonPath('data.0.batches.0.status', 'on_sale')
        ->assertJsonPath('data.0.batches.0.price', ['amount' => 15_000, 'currency' => 'BRL'])
        ->assertJsonPath('data.0.batches.1.status', 'scheduled')
        ->assertJsonPath('data.0.batches.1.status_label', 'Agendado')
        ->assertJsonPath('meta.capacity', null);
});

it('valida código de acesso, mínimo e máximo, janela e nome repetido', function (): void {
    $this->postJson("{$this->base}/ticket-types", ['name' => 'Convidado', 'visibility' => 'code'])
        ->assertStatus(422)->assertJsonPath('errors.access_code.0', 'Informe o código que libera este ingresso.');
    $this->postJson("{$this->base}/ticket-types", ['name' => 'Grupo', 'min_per_order' => 5, 'max_per_order' => 2])
        ->assertStatus(422)->assertJsonPath('errors.max_per_order.0', 'O máximo por pedido precisa ser maior ou igual ao mínimo.');

    $type = createType($this);
    $this->postJson("{$this->base}/ticket-types", ['name' => 'Geral'])->assertStatus(422)->assertJsonValidationErrors(['name']);
    $this->postJson("{$this->base}/ticket-types/{$type}/batches", ['name' => 'X', 'price_cents' => 100, 'quantity' => 1, 'starts_at' => '2027-07-01', 'ends_at' => '2027-06-01'])
        ->assertStatus(422)->assertJsonPath('errors.ends_at.0', 'O fim das vendas precisa ser depois do início.');

    $code = createType($this, ['name' => 'Comunidade', 'visibility' => 'code', 'access_code' => 'phppb2027']);
    $this->getJson("{$this->base}/ticket-types")->assertJsonPath('data.1.access_code', 'PHPPB2027');
    $this->patchJson("{$this->base}/ticket-types/{$code}", ['visibility' => 'public'])->assertOk()->assertJsonPath('data.access_code', null);
});

it('vira para o próximo lote ao esgotar e o preço alocado não muda depois', function (): void {
    $type = createType($this, ['max_per_order' => 5]);
    $first = createBatch($this, $type, ['quantity' => 3]);
    $second = createBatch($this, $type, ['name' => '2º lote', 'price_cents' => 20_000, 'starts_at' => '2027-07-01T00:00:00Z']);

    $allocation = resolve(AllocateTickets::class)->handle($this->edition->id, $first, 3);
    expect($allocation->unitPriceCents)->toBe(15_000);

    $this->patchJson("{$this->base}/ticket-types/{$type}/batches/{$first}", ['price_cents' => 18_000])->assertOk();
    expect($allocation->unitPriceCents)->toBe(15_000);

    $this->getJson("{$this->base}/ticket-types")
        ->assertJsonPath('data.0.batches.0.status', 'sold_out')
        ->assertJsonPath('data.0.batches.1.status', 'on_sale')
        ->assertJsonPath('data.0.current_batch_id', $second);

    $this->patchJson("{$this->base}/ticket-types/{$type}/batches/{$first}", ['quantity' => 2])
        ->assertStatus(422)->assertJsonPath('errors.quantity.0', 'Este lote já tem 3 ingressos vendidos ou reservados; a quantidade não pode ser menor.');
    $this->deleteJson("{$this->base}/ticket-types/{$type}/batches/{$first}")->assertStatus(422);
    $this->deleteJson("{$this->base}/ticket-types/{$type}")->assertStatus(422);
});

it('o job encerra lote vencido e abre o próximo, ou encerra as vendas', function (): void {
    $type = createType($this);
    createBatch($this, $type, ['ends_at' => '2027-06-15T00:00:00Z']);
    $second = createBatch($this, $type, ['name' => '2º lote', 'starts_at' => '2027-07-01T00:00:00Z']);
    $closing = createType($this, ['name' => 'Estudante']);
    createBatch($this, $closing, ['name' => 'Único', 'ends_at' => '2027-06-15T00:00:00Z', 'on_sell_out' => 'close']);
    createBatch($this, $closing, ['name' => 'Nunca abre', 'starts_at' => '2027-08-01T00:00:00Z']);

    $this->travelTo('2027-06-16 08:00:00');
    $this->artisan('ticketing:rotate-batches')->assertSuccessful();

    $this->getJson("{$this->base}/ticket-types")
        ->assertJsonPath('data.0.current_batch_id', $second)
        ->assertJsonPath('data.0.batches.1.opens_at', '2027-06-16T08:00:00Z')
        ->assertJsonPath('data.1.current_batch_id', null)
        ->assertJsonPath('data.1.batches.1.status', 'scheduled');
});

it('respeita a capacidade da edição, mínimo e máximo por pedido, e libera o que foi reservado', function (): void {
    $this->patchJson("{$this->base}/settings", ['capacity' => 4])->assertOk()->assertJsonPath('data.capacity', 4);
    $type = createType($this, ['min_per_order' => 2, 'max_per_order' => 3]);
    $batch = createBatch($this, $type, ['quantity' => 100]);
    $allocate = resolve(AllocateTickets::class);

    expect(fn () => $allocate->handle($this->edition->id, $batch, 1))->toThrow(ValidationException::class);
    $first = $allocate->handle($this->edition->id, $batch, 3);
    expect(fn () => $allocate->handle($this->edition->id, $batch, 2))->toThrow(ValidationException::class, 'Os ingressos da edição esgotaram.');
    expect(TicketBatch::query()->forEdition($this->edition->id)->find($batch)->allocated)->toBe(3);

    $this->patchJson("{$this->base}/settings", ['capacity' => 2])->assertStatus(422)->assertJsonValidationErrors(['capacity']);

    resolve(ReleaseTickets::class)->handle($first);
    $this->getJson("{$this->base}/ticket-types")->assertJsonPath('meta.allocated', 0)->assertJsonPath('meta.remaining', 4);
});

it('a página pública mostra os ingressos públicos e libera os de código, nunca os ocultos', function (): void {
    $public = createType($this, ['show_remaining' => true]);
    createBatch($this, $public, ['quantity' => 10]);
    $coded = createType($this, ['name' => 'Comunidade', 'visibility' => 'code', 'access_code' => 'PHPPB']);
    createBatch($this, $coded, ['name' => 'Único', 'price_cents' => 8_000]);
    $hidden = createType($this, ['name' => 'Palestrante', 'visibility' => 'hidden']);
    createBatch($this, $hidden, ['name' => 'Convite', 'price_cents' => 0]);
    $scheduled = createType($this, ['name' => 'Apoiador']);
    createBatch($this, $scheduled, ['name' => 'Único', 'price_cents' => 35_000, 'starts_at' => '2027-07-01T00:00:00Z']);

    $url = "/api/v1/public/editions/{$this->edition->id}/tickets";
    $this->app['auth']->forgetGuards();

    $this->getJson($url)
        ->assertOk()
        ->assertJsonCount(2, 'data.ticket_types')
        ->assertJsonPath('data.ticket_types.0.batch.remaining', 10)
        ->assertJsonPath('data.ticket_types.0.batch.price.amount', 15_000)
        ->assertJsonPath('data.ticket_types.1.status', 'scheduled')
        ->assertJsonPath('data.ticket_types.1.next_batch.opens_at', '2027-07-01T00:00:00Z')
        ->assertJsonMissingPath('data.ticket_types.0.access_code');

    $this->getJson("{$url}?code=phppb")
        ->assertJsonCount(3, 'data.ticket_types')
        ->assertJsonPath('data.unlocked', true)
        ->assertJsonPath('data.ticket_types.1.name', 'Comunidade');
    $this->getJson("{$url}?code=errado")->assertJsonCount(2, 'data.ticket_types')->assertJsonPath('data.unlocked', false);

    $draft = Edition::factory()->create();
    $this->getJson("/api/v1/public/editions/{$draft->id}/tickets")->assertNotFound();
});

it('financeiro vê os ingressos mas só a organização altera', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);

    Sanctum::actingAs($finance);
    $this->getJson("{$this->base}/ticket-types")->assertOk();
    $this->postJson("{$this->base}/ticket-types", ['name' => 'X'])->assertForbidden();

    Sanctum::actingAs($reviewer);
    $this->getJson("{$this->base}/ticket-types")->assertForbidden();
});

it('copia tipos, lotes e capacidade para a próxima edição, sem datas nem vendas', function (): void {
    $this->patchJson("{$this->base}/settings", ['capacity' => 400])->assertOk();
    $type = createType($this, ['questions' => [['label' => 'Empresa', 'type' => 'text']]]);
    $batch = createBatch($this, $type, ['starts_at' => '2027-06-01T00:00:00Z', 'ends_at' => '2027-06-30T00:00:00Z', 'quantity' => 5]);
    resolve(AllocateTickets::class)->handle($this->edition->id, $batch, 2);

    $next = Edition::factory()->create();
    resolve(TicketingCloner::class)->clone($this->edition->id, $next->id);

    $copy = resolve(CurrentEdition::class)->run($next->id, fn () => TicketType::query()->with('batches')->sole());
    expect($copy->questions[0]['id'])->toBe('empresa')
        ->and($copy->batches->first()->allocated)->toBe(0)
        ->and($copy->batches->first()->starts_at)->toBeNull()
        ->and($copy->batches->first()->price_cents)->toBe(15_000)
        ->and(TicketingSetting::query()->forEdition($next->id)->value('capacity'))->toBe(400);
});
