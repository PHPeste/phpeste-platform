<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Data\PixCharge;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    $this->batch = TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    $this->account = PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $this->checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";

    $order = $this->postJson($this->checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $this->type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-1'])->assertCreated()->json('data');

    $this->order = $order;
    $this->headers = ['X-Order-Token' => $order['access_token']];
    $this->payment = "{$this->checkout}/{$order['id']}/payment";
});

it('Pix: cria a cobrança na conta da edição, deixa o pedido aguardando e confirma quando o pagamento cai', function (): void {
    $this->postJson($this->payment, ['method' => 'pix'], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.method', 'pix')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount.amount', 15_000)
        ->assertJsonPath('data.expires_at', '2027-06-10T12:30:00Z')
        ->assertJsonPath('data.order_status', 'awaiting_payment')
        ->assertJsonPath('data.checkout_url', null);

    $call = collect(FakeGateway::$calls)->firstWhere('method', 'createPixCharge');
    expect($call['api_key'])->toBe('fake_key_1234')->and($call['arguments']['reference'])->toBe($this->order['id']);

    $charge = PaymentCharge::query()->forEdition($this->edition->id)->sole();
    FakeGateway::pay($charge->gateway_charge_id);

    $this->getJson($this->payment, $this->headers)->assertJsonPath('data.status', 'pending');

    $this->travel(6)->seconds();
    $this->getJson($this->payment, $this->headers)
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.order_status', 'paid')
        ->assertJsonPath('data.paid_at', '2027-06-10T12:00:06Z');

    $this->getJson("{$this->checkout}/{$this->order['id']}", $this->headers)->assertJsonPath('data.status', 'paid');
    expect(collect(FakeGateway::$calls)->where('method', 'fetch')->count())->toBe(1);
});

it('clique duplo não cria duas cobranças, e trocar de método cancela a anterior no gateway', function (): void {
    $first = $this->postJson($this->payment, ['method' => 'pix'], $this->headers)->json('data.id');
    $again = $this->postJson($this->payment, ['method' => 'pix'], $this->headers)->json('data.id');

    expect($again)->toBe($first)
        ->and(collect(FakeGateway::$calls)->where('method', 'createPixCharge')->count())->toBe(1);

    $this->postJson($this->payment, ['method' => 'card', 'installments' => 3], $this->headers)
        ->assertOk()
        ->assertJsonPath('data.method', 'card')
        ->assertJsonPath('data.installments', 3)
        ->assertJsonPath('data.pix', null)
        ->assertJsonPath('data.checkout_url', fn (string $url): bool => str_starts_with($url, 'https://fake-gateway.test/i/'));

    $hosted = collect(FakeGateway::$calls)->firstWhere('method', 'createHostedCheckout');
    expect($hosted['arguments']['return_url'])->toEndWith("/checkout/pedido/{$this->order['id']}")
        ->and(PaymentCharge::query()->forEdition($this->edition->id)->where('method', 'pix')->value('status'))->toBe(ChargeStatus::Cancelled);
});

it('dados de cartão nunca são aceitos pela plataforma', function (): void {
    Log::spy();

    $this->postJson($this->payment, ['method' => 'card', 'card_number' => '4111111111111111', 'cvv' => '123'], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.card_number.0', 'Os dados do cartão nunca são enviados para a plataforma: eles são digitados só na página do meio de pagamento.')
        ->assertJsonMissingPath('errors.card_number.1');

    expect(PaymentCharge::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and(json_encode(FakeGateway::$calls))->not->toContain('4111111111111111');
});

it('respeita os métodos e as parcelas da conta', function (): void {
    $this->postJson($this->payment, ['method' => 'boleto'], $this->headers)->assertStatus(422)->assertJsonPath('errors.method.0', 'Boleto não está disponível nesta edição.');
    $this->postJson($this->payment, ['method' => 'card', 'installments' => 6], $this->headers)->assertStatus(422)->assertJsonPath('errors.installments.0', 'Escolha de 1 a 3 parcelas.');
    $this->postJson($this->payment, ['method' => 'pix', 'installments' => 2], $this->headers)->assertStatus(422)->assertJsonPath('errors.installments.0', 'Só cartão pode ser parcelado.');
});

it('erro do gateway vira mensagem clara para quem compra, sem detalhe interno, e o pedido continua aberto', function (): void {
    $this->app->bind(FakeGateway::class, fn (): FakeGateway => new class extends FakeGateway
    {
        public function createPixCharge($credentials, $charge): PixCharge
        {
            throw new GatewayRequestFailed('Asaas respondeu 500 em post /payments: NullPointerException at line 42', GatewayRequestFailed::UNAVAILABLE, 500);
        }
    });

    $response = $this->postJson($this->payment, ['method' => 'pix'], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.payment.0', 'O meio de pagamento está fora do ar agora. Tente de novo em alguns minutos; sua reserva continua valendo.');

    expect($response->getContent())->not->toContain('NullPointer')->not->toContain('Asaas')->not->toContain('500 em')
        ->and(Order::query()->forEdition($this->edition->id)->value('status')->value)->toBe('created');
});

it('reserva expirada cancela a cobrança pendente no gateway e não aceita novo pagamento', function (): void {
    $this->postJson($this->payment, ['method' => 'pix'], $this->headers)->assertOk();
    $charge = PaymentCharge::query()->forEdition($this->edition->id)->sole();

    $this->travelTo('2027-06-10 12:31:00');
    $this->artisan('ticketing:expire-orders')->assertSuccessful();

    expect($charge->fresh()->status)->toBe(ChargeStatus::Cancelled)
        ->and(FakeGateway::$charges[$charge->gateway_charge_id]['status'])->toBe(ChargeStatus::Cancelled)
        ->and($this->batch->fresh()->allocated)->toBe(0);

    $this->postJson($this->payment, ['method' => 'pix'], $this->headers)->assertStatus(422)->assertJsonPath('errors.payment.0', 'Este pedido está expirado e não aceita pagamento.');
});

it('pagamento que chega depois de o pedido expirar não reabre o pedido e fica registrado', function (): void {
    Log::spy();
    $this->postJson($this->payment, ['method' => 'card'], $this->headers)->assertOk();
    $charge = PaymentCharge::query()->forEdition($this->edition->id)->sole();

    $this->travelTo('2027-06-10 12:31:00');
    Order::query()->forEdition($this->edition->id)->whereKey($this->order['id'])->update(['status' => 'expired']);
    FakeGateway::pay($charge->gateway_charge_id);

    resolve(PaymentProcessor::class)->current($this->edition->id, $this->order['id'], refresh: true);

    expect($charge->fresh()->status)->toBe(ChargeStatus::Paid)
        ->and(Order::query()->forEdition($this->edition->id)->value('status')->value)->toBe('expired');
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'ticketing.charge_without_order_transition' && $context['charge_status'] === 'paid')->once();
});

it('sem o token do pedido, ninguém inicia nem consulta o pagamento', function (): void {
    $this->postJson($this->payment, ['method' => 'pix'])->assertNotFound();
    $this->getJson($this->payment, ['X-Order-Token' => 'errado'])->assertNotFound();
    $this->getJson($this->payment, $this->headers)->assertOk()->assertJsonPath('data', null);
});
