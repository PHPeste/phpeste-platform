<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;
use PHPeste\Ticketing\Orders\SalesReadinessGuard;

const CPF = '529.982.247-25';
const OTHER_CPF = '111.444.777-35';

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $this->settings = TicketingSetting::create(['edition_id' => $this->edition->id, 'gateway_fee_basis_points' => 360]);
    $this->general = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral', 'max_per_order' => 5, 'questions' => [
        ['id' => 'nome_no_cracha', 'label' => 'Nome no crachá', 'type' => 'text', 'required' => true, 'options' => []],
        ['id' => 'camiseta', 'label' => 'Camiseta', 'type' => 'select', 'required' => false, 'options' => ['P', 'M', 'G']],
    ]]);
    $this->student = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Estudante', 'pass_fee_to_buyer' => true]);
    $this->generalBatch = TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->general->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 100]);
    $this->studentBatch = TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->student->id, 'name' => 'Único', 'price_cents' => 7_500, 'quantity' => 2]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana, financeiro da PHPeste', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $this->checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
});

function attendee(string $name = 'Ana Souza', array $answers = ['nome_no_cracha' => 'Ana']): array
{
    return ['name' => $name, 'email' => mb_strtolower(str_replace(' ', '.', $name)).'@exemplo.com', 'answers' => $answers];
}

function orderPayload(object $test, array $overrides = []): array
{
    return [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'Ana@Exemplo.com', 'document' => CPF],
        'items' => [['ticket_type_id' => $test->general->id, 'attendees' => [attendee()]]],
        ...$overrides,
    ];
}

function placeOrder(object $test, array $payload, string $key = 'chave-1'): TestResponse
{
    return $test->postJson($test->checkout, $payload, ['Idempotency-Key' => $key]);
}

it('cria o pedido reservando o estoque, com desconto, taxa repassada e preço guardado por ingresso', function (): void {
    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'PHPPB', 'kind' => 'percent', 'value' => 10]);

    $response = placeOrder($this, orderPayload($this, [
        'coupon' => 'phppb',
        'items' => [
            ['ticket_type_id' => $this->general->id, 'attendees' => [attendee(), attendee('Bia Lima', ['nome_no_cracha' => 'Bia', 'camiseta' => 'M'])]],
            ['ticket_type_id' => $this->student->id, 'attendees' => [attendee('Caio Reis', [])]],
        ],
    ]))
        ->assertCreated()
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.buyer', ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '***.***.247-**'])
        ->assertJsonPath('data.subtotal.amount', 37_500)
        ->assertJsonPath('data.discount.amount', 3_750)
        ->assertJsonPath('data.fee.amount', 243)
        ->assertJsonPath('data.total.amount', 33_993)
        ->assertJsonPath('data.expires_at', '2027-06-10T12:30:00Z')
        ->assertJsonPath('data.items.0.total.amount', 13_500)
        ->assertJsonPath('data.items.1.answers', ['nome_no_cracha' => 'Bia', 'camiseta' => 'M'])
        ->assertJsonPath('data.items.2.fee.amount', 243)
        ->assertJsonPath('data.items.2.total.amount', 6_993);

    expect($this->generalBatch->fresh()->allocated)->toBe(2)
        ->and($this->studentBatch->fresh()->allocated)->toBe(1)
        ->and(Coupon::query()->forEdition($this->edition->id)->value('used'))->toBe(1);

    $id = $response->json('data.id');
    $token = $response->json('data.access_token');

    $this->getJson("{$this->checkout}/{$id}", ['X-Order-Token' => $token])->assertOk()->assertJsonPath('data.number', 1)->assertJsonMissingPath('data.access_token');
    $this->getJson("{$this->checkout}/{$id}", ['X-Order-Token' => 'errado'])->assertNotFound();
    $this->getJson("{$this->checkout}/{$id}")->assertNotFound();

    $this->generalBatch->update(['price_cents' => 20_000]);
    $this->getJson("{$this->checkout}/{$id}", ['X-Order-Token' => $token])->assertJsonPath('data.items.0.unit_price.amount', 15_000);
});

it('reenviar com a mesma Idempotency-Key devolve o mesmo pedido, sem reservar de novo', function (): void {
    $first = placeOrder($this, orderPayload($this))->assertCreated();
    $again = placeOrder($this, orderPayload($this))->assertOk();

    expect($again->json('data.id'))->toBe($first->json('data.id'))
        ->and($again->json('data.access_token'))->toBe($first->json('data.access_token'))
        ->and(Order::query()->forEdition($this->edition->id)->count())->toBe(1)
        ->and($this->generalBatch->fresh()->allocated)->toBe(1);

    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Outra Pessoa', 'email' => 'outra@exemplo.com', 'document' => CPF]]))
        ->assertStatus(409)->assertJsonPath('message', 'Esta Idempotency-Key já foi usada com outros dados de pedido.');

    $this->postJson($this->checkout, orderPayload($this))->assertStatus(400)->assertJsonPath('code', 'idempotency_key_required');

    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Ana', 'email' => 'ana@exemplo.com', 'document' => '123.456.789-00']]), 'chave-2')
        ->assertStatus(422)->assertJsonPath('errors', ['buyer.document' => ['Informe um CPF válido.']]);
    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Ana', 'email' => 'ana@exemplo.com', 'document' => OTHER_CPF]]), 'chave-2')->assertCreated();
});

it('cortesia de 100% vira pedido pago na hora, sem gateway e sem CPF', function (): void {
    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'CORTESIA', 'kind' => 'percent', 'value' => 100, 'max_uses' => 1]);

    placeOrder($this, orderPayload($this, ['coupon' => 'cortesia', 'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.total.amount', 0)
        ->assertJsonPath('data.requires_payment', false)
        ->assertJsonPath('data.expires_at', null)
        ->assertJsonPath('data.paid_at', '2027-06-10T12:00:00Z');

    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']]), 'chave-2')
        ->assertStatus(422)->assertJsonPath('errors.buyer_document.0', 'Informe o CPF de quem paga.');
    expect($this->generalBatch->fresh()->allocated)->toBe(1);
});

it('a reserva expirada devolve o ingresso e o cupom para a venda', function (): void {
    $this->settings->update(['reservation_minutes' => 15]);
    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'UNICO', 'kind' => 'fixed', 'value' => 1_000, 'max_uses' => 1]);
    $order = placeOrder($this, orderPayload($this, ['coupon' => 'unico']))->assertJsonPath('data.expires_at', '2027-06-10T12:15:00Z')->json('data');

    $this->travelTo('2027-06-10 12:14:00');
    $this->artisan('ticketing:expire-orders')->expectsOutput('0 pedidos expirados.')->assertSuccessful();

    $this->travelTo('2027-06-10 12:15:00');
    $this->artisan('ticketing:expire-orders')->expectsOutput('1 pedidos expirados.')->assertSuccessful();

    $this->getJson("{$this->checkout}/{$order['id']}", ['X-Order-Token' => $order['access_token']])->assertJsonPath('data.status', 'expired');
    expect($this->generalBatch->fresh()->allocated)->toBe(0)
        ->and(TicketingSetting::query()->forEdition($this->edition->id)->value('allocated'))->toBe(0)
        ->and(Coupon::query()->forEdition($this->edition->id)->value('used'))->toBe(0);

    $this->postJson("{$this->checkout}/{$order['id']}/cancel", [], ['X-Order-Token' => $order['access_token']])
        ->assertStatus(422)->assertJsonPath('errors.status.0', 'Um pedido expirado não pode passar para cancelado.');
    placeOrder($this, orderPayload($this, ['coupon' => 'unico']), 'chave-2')->assertCreated();
});

it('aceita só as transições válidas da máquina de estados', function (OrderStatus $from, OrderStatus $to, bool $allowed): void {
    $order = Order::query()->forEdition($this->edition->id)->findOrFail(placeOrder($this, orderPayload($this))->json('data.id'));
    Order::query()->forEdition($this->edition->id)->whereKey($order->id)->update(['status' => $from->value]);
    $order->refresh();

    $attempt = fn () => resolve(TransitionOrder::class)->handle($order, $to);

    if ($allowed) {
        expect($attempt()->status)->toBe($to);
    } else {
        expect($attempt)->toThrow(ValidationException::class);
        expect($order->fresh()->status)->toBe($from);
    }
})->with(function (): array {
    $cases = [];

    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            $cases["{$from->value} → {$to->value}"] = [$from, $to, in_array($to, $from->next(), true)];
        }
    }

    return $cases;
});

it('quem compra cancela a própria reserva, e uma transição concorrente não roda duas vezes', function (): void {
    $order = placeOrder($this, orderPayload($this))->json('data');

    $this->postJson("{$this->checkout}/{$order['id']}/cancel", [], ['X-Order-Token' => $order['access_token']])->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($this->generalBatch->fresh()->allocated)->toBe(0);

    $stale = Order::query()->forEdition($this->edition->id)->findOrFail($order['id']);
    $stale->status = OrderStatus::Created;

    expect(fn () => resolve(TransitionOrder::class)->handle($stale, OrderStatus::Expired))->toThrow(ValidationException::class, 'O pedido mudou enquanto isso.');
    expect($this->generalBatch->fresh()->allocated)->toBe(0);
});

it('limita ingressos por e-mail ou CPF, sem contar pedidos cancelados ou expirados', function (): void {
    $this->settings->update(['max_tickets_per_buyer' => 2]);

    $first = placeOrder($this, orderPayload($this, ['items' => [['ticket_type_id' => $this->general->id, 'attendees' => [attendee(), attendee('Bia Lima')]]]]))->assertCreated()->json('data');

    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Ana', 'email' => 'outro@exemplo.com', 'document' => CPF]]), 'chave-2')
        ->assertStatus(422)->assertJsonPath('errors.items.0', 'Cada pessoa compra no máximo 2 ingressos nesta edição, e esse limite já foi atingido com este e-mail ou CPF.');
    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Ana', 'email' => 'ANA@exemplo.com', 'document' => OTHER_CPF]]), 'chave-3')->assertStatus(422);

    $this->postJson("{$this->checkout}/{$first['id']}/cancel", [], ['X-Order-Token' => $first['access_token']])->assertOk();
    placeOrder($this, orderPayload($this), 'chave-4')->assertCreated();
    placeOrder($this, orderPayload($this, ['items' => [['ticket_type_id' => $this->general->id, 'attendees' => [attendee(), attendee('Bia Lima')]]]]), 'chave-5')
        ->assertStatus(422)->assertJsonPath('errors.items.0', 'Cada pessoa compra no máximo 2 ingressos nesta edição; com este e-mail ou CPF ainda dá para comprar 1.');
});

it('valida as perguntas do checkout por participante', function (): void {
    placeOrder($this, orderPayload($this, ['items' => [['ticket_type_id' => $this->general->id, 'attendees' => [
        attendee('Ana Souza', []),
        attendee('Bia Lima', ['nome_no_cracha' => 'Bia', 'camiseta' => 'GG']),
    ]]]]))
        ->assertStatus(422)
        ->assertJsonPath('errors', [
            'items.0.attendees.0.answers.nome_no_cracha' => ['Responda "Nome no crachá".'],
            'items.0.attendees.1.answers.camiseta' => ['Escolha uma das opções de "Camiseta".'],
        ]);

    expect($this->generalBatch->fresh()->allocated)->toBe(0);
});

it('recusa ingresso oculto, exige o código do ingresso por código e respeita o estado da edição', function (): void {
    $hidden = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Palestrante', 'visibility' => 'hidden']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $hidden->id, 'name' => 'Convite', 'price_cents' => 0, 'quantity' => 10]);
    $coded = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Comunidade', 'visibility' => 'code', 'access_code' => 'PHPPB']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $coded->id, 'name' => 'Único', 'price_cents' => 8_000, 'quantity' => 10]);

    placeOrder($this, orderPayload($this, ['items' => [['ticket_type_id' => $hidden->id, 'attendees' => [attendee()]]]]))
        ->assertStatus(422)->assertJsonPath('errors', ['items.0.ticket_type_id' => ['Este ingresso não está disponível.']]);
    placeOrder($this, orderPayload($this, ['items' => [['ticket_type_id' => $coded->id, 'attendees' => [attendee()]]]]), 'chave-2')->assertStatus(422);
    placeOrder($this, orderPayload($this, ['access_code' => 'phppb', 'items' => [['ticket_type_id' => $coded->id, 'attendees' => [attendee()]]]]), 'chave-3')->assertCreated();

    $cfp = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $this->postJson("/api/v1/checkout/editions/{$cfp->id}/orders", orderPayload($this), ['Idempotency-Key' => 'x'])
        ->assertStatus(422)->assertJsonPath('errors.items.0', 'As vendas desta edição não estão abertas.');
    $this->postJson('/api/v1/checkout/editions/'.Edition::factory()->create()->id.'/orders', orderPayload($this), ['Idempotency-Key' => 'x'])->assertNotFound();
});

it('sem estoque para um item, nada é reservado nem criado', function (): void {
    placeOrder($this, orderPayload($this, ['items' => [
        ['ticket_type_id' => $this->general->id, 'attendees' => [attendee()]],
        ['ticket_type_id' => $this->student->id, 'attendees' => [attendee('Bia', []), attendee('Caio', []), attendee('Davi', [])]],
    ]]))->assertStatus(422);

    expect(Order::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and($this->generalBatch->fresh()->allocated)->toBe(0)
        ->and($this->studentBatch->fresh()->allocated)->toBe(0)
        ->and(TicketingSetting::query()->forEdition($this->edition->id)->value('allocated'))->toBe(0)
        ->and(TicketingSetting::query()->forEdition($this->edition->id)->value('order_sequence'))->toBe(0);
});

it('a organização lista, abre e cancela pedidos com auditoria; o financeiro só vê', function (): void {
    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);

    $first = placeOrder($this, orderPayload($this))->json('data.id');
    placeOrder($this, orderPayload($this, ['buyer' => ['name' => 'Bruno Teles', 'email' => 'bruno@exemplo.com', 'document' => OTHER_CPF]]), 'chave-2');
    $admin = "/api/v1/editions/{$this->edition->id}/ticketing/orders";

    Sanctum::actingAs($finance);
    $this->getJson($admin)->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.number', 2)->assertJsonMissingPath('data.0.access_token');
    $this->getJson("{$admin}?q=bruno")->assertJsonCount(1, 'data');
    $this->getJson("{$admin}?q=%231")->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 1);
    $this->postJson("{$admin}/{$first}/cancel")->assertForbidden();

    Sanctum::actingAs($organizer);
    $this->postJson("{$admin}/{$first}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->getJson("{$admin}?status=cancelled")->assertJsonCount(1, 'data');

    $log = AuditLog::query()->where('action', 'ticketing.order_status_changed')->where('subject_id', $first)->sole();
    expect($log->actor_id)->toBe($organizer->id)->and($log->after['status'])->toBe('cancelled');
});

it('a visão geral mostra ingressos vendidos, receita e pedidos aguardando pagamento', function (): void {
    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'CORTESIA', 'kind' => 'percent', 'value' => 100]);
    $this->settings->update(['capacity' => 50]);

    placeOrder($this, orderPayload($this));
    placeOrder($this, orderPayload($this, ['coupon' => 'cortesia']), 'chave-2');
    $paid = Order::query()->forEdition($this->edition->id)->where('number', 1)->sole();
    resolve(TransitionOrder::class)->handle($paid, OrderStatus::Paid);

    Sanctum::actingAs($organizer);
    $this->getJson("/api/v1/editions/{$this->edition->id}/overview")
        ->assertOk()
        ->assertJsonPath('data.indicators.tickets', ['sold' => 2, 'available' => 50])
        ->assertJsonPath('data.indicators.revenue', ['gross' => 15_000, 'net' => 14_460])
        ->assertJsonPath('data.sections.tickets_per_week.7', 2);
});

it('pedido pago guarda a conta que recebe e fica pausado enquanto o gateway não foi testado', function (): void {
    $account = PaymentAccount::query()->forEdition($this->edition->id)->sole();
    $id = placeOrder($this, orderPayload($this))->assertCreated()->assertJsonPath('data.payment_account_id', $account->id)->json('data.id');
    expect(Order::query()->forEdition($this->edition->id)->find($id)->payment_account_id)->toBe($account->id);

    PaymentAccount::query()->forEdition($this->edition->id)->update(['webhook_verified_at' => null]);
    placeOrder($this, orderPayload($this), 'chave-2')->assertStatus(422)->assertJsonPath('errors.items.0', 'Vendas pausadas até o teste do gateway.');

    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'CORTESIA', 'kind' => 'percent', 'value' => 100]);
    placeOrder($this, orderPayload($this, ['coupon' => 'cortesia']), 'chave-3')->assertCreated()->assertJsonPath('data.payment_account_id', null);
});

it('só abre vendas com lote pago quando a conta do gateway está validada e o webhook testado', function (): void {
    $organizer = User::factory()->create();
    $draft = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    EditionMember::factory()->for($draft)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $status = "/api/v1/editions/{$draft->id}/status";

    $this->postJson($status, ['status' => 'sales_open'])->assertOk();
    Edition::query()->whereKey($draft->id)->update(['status' => EditionStatus::CfpOpen->value]);

    $type = TicketType::create(['edition_id' => $draft->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $draft->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    $this->postJson($status, ['status' => 'sales_open'])
        ->assertStatus(422)->assertJsonPath('errors.status.0', SalesReadinessGuard::MESSAGE);

    PaymentAccount::create(['edition_id' => $draft->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_9999', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '9999', 'holder_type' => 'company', 'holder_name' => 'Associação PHP PB', 'holder_document_masked' => '**.***.678/0001-**', 'methods' => ['pix'], 'webhook_token' => str_repeat('b', 48), 'verified_at' => now()]);
    $this->postJson($status, ['status' => 'sales_open'])->assertStatus(422);

    PaymentAccount::query()->forEdition($draft->id)->update(['webhook_verified_at' => now()]);
    $this->postJson($status, ['status' => 'sales_open'])->assertOk()->assertJsonPath('data.status', 'sales_open');
});
