<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $this->checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
    $order = $this->postJson($this->checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'Ana@Exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $this->type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com'], ['name' => 'Bia Lima', 'email' => 'bia@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-1'])->assertCreated()->json('data');
    $headers = ['X-Order-Token' => $order['access_token']];
    $this->postJson("{$this->checkout}/{$order['id']}/payment", ['method' => 'pix'], $headers)->assertOk();
    FakeGateway::pay(PaymentCharge::query()->forEdition($this->edition->id)->sole()->gateway_charge_id);
    $this->travel(6)->seconds();
    $this->getJson("{$this->checkout}/{$order['id']}/payment", $headers)->assertJsonPath('data.order_status', 'paid');

    $this->orderId = $order['id'];
    $this->mine = "/api/v1/attendee/editions/{$this->edition->id}/orders";
});

it('quem comprou com o e-mail verificado vê o pedido, os itens e os reembolsos, sem CPF nem dados internos', function (): void {
    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $this->postJson("/api/v1/editions/{$this->edition->id}/ticketing/orders/{$this->orderId}/refunds", ['reason' => 'buyer_request', 'note' => 'Pediu pelo Instagram', 'amount_cents' => 15_000])->assertCreated();

    Sanctum::actingAs(User::factory()->create(['email' => 'ana@exemplo.com']));
    $response = $this->getJson($this->mine)->assertOk()
        ->assertJsonPath('meta.email_verified', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->orderId)
        ->assertJsonPath('data.0.status', 'paid')
        ->assertJsonPath('data.0.total.amount', 30_000)
        ->assertJsonPath('data.0.refunded.amount', 15_000)
        ->assertJsonPath('data.0.items.1.attendee_name', 'Bia Lima')
        ->assertJsonPath('data.0.refunds.0.amount.amount', 15_000)
        ->assertJsonPath('data.0.refunds.0.status', 'completed')
        ->assertJsonPath('data.0.refunds.0.reason_label', 'Pedido de quem comprou');

    expect($response->json('data.0'))->not->toHaveKeys(['buyer', 'gateway_fee', 'payment_account_id', 'access_token'])
        ->and($response->json('data.0.refunds.0'))->not->toHaveKeys(['note', 'requested_by']);
});

it('pedido feito logado aparece mesmo sem o e-mail verificado, e o de outra pessoa nunca', function (): void {
    $caio = User::factory()->unverified()->create(['email' => 'caio@exemplo.com']);
    Sanctum::actingAs($caio);
    $this->postJson($this->checkout, [
        'buyer' => ['name' => 'Caio', 'email' => 'caio@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $this->type->id, 'attendees' => [['name' => 'Caio', 'email' => 'caio@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-2'])->assertCreated();

    $this->getJson($this->mine)->assertOk()
        ->assertJsonPath('meta.email_verified', false)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'created')
        ->assertJsonPath('data.0.refunds', []);

    Sanctum::actingAs(User::factory()->unverified()->create(['email' => 'ana@exemplo.com']));
    $this->getJson($this->mine)->assertOk()->assertJsonCount(0, 'data');
});

it('sem sessão não lista pedidos', function (): void {
    $this->getJson($this->mine)->assertUnauthorized();
});
