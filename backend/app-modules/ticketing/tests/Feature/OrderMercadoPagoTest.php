<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Tests\Support\MercadoPagoHarness;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $type = TicketType::create(['edition_id' => $edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $edition->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_540, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $edition->id, 'driver' => 'mercadopago', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'APP_USR-1234567890-chave-de-teste', 'webhook_secret' => MercadoPagoHarness::SECRET, 'public_key' => 'APP_USR-pub'], 'credentials_hint' => 'este', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 6, 'webhook_token' => str_repeat('m', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $checkout = "/api/v1/checkout/editions/{$edition->id}/orders";
    $order = $this->postJson($checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-mp'])->assertCreated()->json('data');

    $this->editionId = $edition->id;
    $this->orderId = $order['id'];
    $this->payment = "{$checkout}/{$order['id']}/payment";
    $this->headers = ['X-Order-Token' => $order['access_token']];
});

it('cartão pelo formulário do Mercado Pago confirma o pedido na hora e guarda a taxa', function (): void {
    Http::fake(['https://api.mercadopago.com/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-card-approved'), 201)]);

    $this->postJson($this->payment, ['method' => 'card', 'installments' => 3, 'card_token' => 'tok_mp_123', 'card_brand' => 'master', 'card_issuer' => '24'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.order_status', 'paid')
        ->assertJsonPath('data.checkout_url', null);

    Http::assertSent(fn (Request $request): bool => $request['token'] === 'tok_mp_123' && $request['payment_method_id'] === 'master' && $request['issuer_id'] === '24' && $request['installments'] === 3);
    expect(Order::query()->forEdition($this->editionId)->findOrFail($this->orderId)->gateway_fee_cents)->toBe(774);
});

it('cartão recusado mostra o motivo e deixa tentar de novo; sem o token, pede para preencher o cartão', function (): void {
    Http::fake(['https://api.mercadopago.com/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-card-rejected'), 201)]);

    $this->postJson($this->payment, ['method' => 'card', 'installments' => 3, 'card_token' => 'tok_mp_123', 'card_brand' => 'master'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'Cartão sem limite para esta compra.')
        ->assertJsonPath('data.order_status', 'awaiting_payment');

    $this->postJson($this->payment, ['method' => 'card', 'installments' => 1], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.card_token.0', 'Os dados do cartão não chegaram. Preencha o cartão de novo.');
});

it('o webhook assinado do Mercado Pago entra na caixa e confirma o Pix pela consulta', function (): void {
    Http::fake([
        'https://api.mercadopago.com/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-pix'), 201),
        'https://api.mercadopago.com/v1/payments/1325467890' => Http::response([...MercadoPagoHarness::fixture('payment-approved'), 'transaction_amount' => 155.4]),
    ]);
    $this->postJson($this->payment, ['method' => 'pix'], $this->headers)->assertOk()->assertJsonPath('data.status', 'pending');

    $body = (string) json_encode(MercadoPagoHarness::fixture('webhook-payment'));
    $server = collect(MercadoPagoHarness::sign('1325467890', 'req-abc-123', '1781089513'))->mapWithKeys(fn (string $value, string $name): array => ['HTTP_'.mb_strtoupper(str_replace('-', '_', $name)) => $value])->all();

    $this->call('POST', '/api/v1/webhooks/payments/'.str_repeat('m', 48), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', ...$server], $body)
        ->assertOk()
        ->assertJsonPath('data.event_id', '118456789012');

    $this->getJson("/api/v1/checkout/editions/{$this->editionId}/orders/{$this->orderId}", $this->headers)->assertJsonPath('data.status', 'paid');
});
