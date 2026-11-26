<?php

declare(strict_types=1);

use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $this->checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
});

function placeTicketsOrder(object $test, array $extra = []): array
{
    return $test->postJson($test->checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $test->type->id, 'attendees' => [
            ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com'],
            ['name' => 'Bia Lima', 'email' => 'bia@exemplo.com'],
        ]]],
        ...$extra,
    ], ['Idempotency-Key' => uniqid('chave-', true)])->assertCreated()->json('data');
}

it('pedido pago mostra os ingressos com QR para quem tem o token do pedido', function (): void {
    Coupon::create(['edition_id' => $this->edition->id, 'code' => 'CORTESIA', 'kind' => 'percent', 'value' => 100]);
    $order = placeTicketsOrder($this, ['coupon' => 'CORTESIA']);

    $tickets = $this->getJson("{$this->checkout}/{$order['id']}/tickets", ['X-Order-Token' => $order['access_token']])
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->json('data');

    expect(array_column(array_column($tickets, 'holder'), 'name'))->toBe(['Ana Souza', 'Bia Lima'])
        ->and($tickets[0]['ticket_type_name'])->toBe('Geral')
        ->and($tickets[0]['qr'])->toStartWith('PHT1.')
        ->and($tickets[0])->not->toHaveKey('holder.accessibility');
});

it('antes de pagar não há ingresso, e sem o token ninguém vê nada', function (): void {
    $order = placeTicketsOrder($this);

    $this->getJson("{$this->checkout}/{$order['id']}/tickets", ['X-Order-Token' => $order['access_token']])->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("{$this->checkout}/{$order['id']}/tickets")->assertNotFound();
    $this->getJson("{$this->checkout}/{$order['id']}/tickets", ['X-Order-Token' => 'errado'])->assertNotFound();

    resolve(TransitionOrder::class)->handle(Order::query()->forEdition($this->edition->id)->findOrFail($order['id']), OrderStatus::Paid, null, 'test');

    $this->getJson("{$this->checkout}/{$order['id']}/tickets", ['X-Order-Token' => $order['access_token']])->assertOk()->assertJsonCount(2, 'data');
});
