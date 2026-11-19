<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Drivers\MercadoPago\MercadoPagoGateway;
use PHPeste\Payments\Drivers\MercadoPago\MercadoPagoStatus;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;
use PHPeste\Payments\Tests\Support\MercadoPagoHarness;

const MP = 'https://api.mercadopago.com';

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->gateway = resolve(MercadoPagoGateway::class);
    $this->credentials = new GatewayCredentials('APP_USR-1234567890-chave-de-teste', MercadoPagoHarness::SECRET, GatewayEnvironment::Sandbox);
    $this->charge = new ChargeRequest('01a1f07d-0000-7000-8000-000000000001', 15_540, 'PHPeste 2027 · pedido #1', new Payer('Ana Souza Lima', 'ana@exemplo.com', '52998224725'), now()->addMinutes(30));
});

it('cria o Pix com token Bearer, chave de idempotência e vencimento no horário de Brasília', function (): void {
    Http::fake([MP.'/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-pix'), 201)]);

    $this->gateway->createPixCharge($this->credentials, $this->charge);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer APP_USR-1234567890-chave-de-teste')
        && $request->hasHeader('X-Idempotency-Key')
        && $request->hasHeader('User-Agent', 'PHPeste-Platform')
        && $request['payment_method_id'] === 'pix'
        && $request['date_of_expiration'] === '2027-06-10T09:30:00.000-03:00'
        && $request['external_reference'] === '01a1f07d-0000-7000-8000-000000000001'
        && $request['payer'] === ['email' => 'ana@exemplo.com', 'first_name' => 'Ana', 'last_name' => 'Souza Lima', 'identification' => ['type' => 'CPF', 'number' => '52998224725']]);
});

it('repete a criação depois de queda de conexão com a mesma chave de idempotência, sem cobrar duas vezes', function (): void {
    Http::fakeSequence(MP.'/v1/payments')->pushFailedConnection()->push(MercadoPagoHarness::fixture('payment-pix'), 201);

    $pix = $this->gateway->createPixCharge($this->credentials, $this->charge);

    $keys = [];
    Http::assertSent(function (Request $request) use (&$keys): bool {
        $keys[] = $request->header('X-Idempotency-Key')[0];

        return true;
    });

    expect($pix->chargeId)->toBe('1325467890')->and($keys)->toHaveCount(2)->and(array_unique($keys))->toHaveCount(1);
});

it('cobra o cartão com o token do formulário do Mercado Pago e guarda só a taxa de quem recebe', function (): void {
    Http::fake([MP.'/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-card-approved'), 201)]);

    $snapshot = $this->gateway->chargeCard($this->credentials, $this->charge, new CardPayment('tok_mp_123', 3, '200.10.10.10', 'master', '24'));

    expect($snapshot->status)->toBe(ChargeStatus::Paid)
        ->and($snapshot->chargeId)->toBe('1325467998')
        ->and($snapshot->feeCents)->toBe(774)
        ->and($snapshot->paidAt?->toIso8601ZuluString())->toBe('2027-06-10T12:01:00Z');
    Http::assertSent(fn (Request $request): bool => $request['token'] === 'tok_mp_123' && $request['installments'] === 3 && $request['payment_method_id'] === 'master' && $request['issuer_id'] === '24' && !isset($request['card_number']));
});

it('cartão recusado volta com o motivo em português, e sem bandeira nem chama a API', function (): void {
    Http::fake([MP.'/v1/payments' => Http::response(MercadoPagoHarness::fixture('payment-card-rejected'), 201)]);

    $snapshot = $this->gateway->chargeCard($this->credentials, $this->charge, new CardPayment('tok_mp_123', 3, null, 'master'));

    expect($snapshot->status)->toBe(ChargeStatus::Failed)->and($snapshot->failureReason)->toBe('Cartão sem limite para esta compra.')
        ->and(fn () => $this->gateway->chargeCard($this->credentials, $this->charge, new CardPayment('tok_mp_123', 1)))->toThrow(GatewayRequestFailed::class)
        ->and(fn () => $this->gateway->createHostedCheckout($this->credentials, $this->charge, PaymentMethod::Card, 1, 'https://exemplo.dev'))->toThrow(GatewayRequestFailed::class);
    Http::assertSentCount(1);
});

it('traduz os status do Mercado Pago para os da plataforma', function (string $status, string $detail, ChargeStatus $expected): void {
    expect(MercadoPagoStatus::map($status, $detail))->toBe($expected);
})->with([
    ['pending', 'pending_waiting_transfer', ChargeStatus::Pending],
    ['in_process', 'pending_review_manual', ChargeStatus::Pending],
    ['authorized', '', ChargeStatus::Pending],
    ['approved', 'accredited', ChargeStatus::Paid],
    ['rejected', 'cc_rejected_other_reason', ChargeStatus::Failed],
    ['cancelled', 'expired', ChargeStatus::Expired],
    ['cancelled', 'by_collector', ChargeStatus::Cancelled],
    ['refunded', 'refunded', ChargeStatus::Refunded],
    ['in_mediation', '', ChargeStatus::Disputed],
    ['charged_back', 'settled', ChargeStatus::Disputed],
]);

it('Pix vencido aparece como vencido na consulta', function (): void {
    Http::fake([MP.'/v1/payments/1325467890' => Http::response(MercadoPagoHarness::fixture('payment-expired'))]);

    expect($this->gateway->fetch($this->credentials, '1325467890')->status)->toBe(ChargeStatus::Expired);
});

it('webhook: confere a assinatura com o id, o request id e o horário, e ignora o que não é pagamento', function (): void {
    $body = (string) json_encode(MercadoPagoHarness::fixture('webhook-payment'));
    $headers = MercadoPagoHarness::sign('1325467890', 'req-abc-123', '1781089513');

    $event = $this->gateway->parseWebhook($this->credentials, $headers, $body);
    expect($event->eventId)->toBe('118456789012')->and($event->type)->toBe('payment.updated')->and($event->chargeId)->toBe('1325467890')->and($event->status)->toBeNull();

    expect(fn () => $this->gateway->parseWebhook($this->credentials, $headers, str_replace('1325467890', '9999999999', $body)))->toThrow(InvalidWebhookSignature::class, 'Assinatura do webhook do Mercado Pago inválida.')
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, [...$headers, 'x-request-id' => 'outro'], $body))->toThrow(InvalidWebhookSignature::class)
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, ['x-signature' => 'v1=abc'], $body))->toThrow(InvalidWebhookSignature::class, 'Webhook do Mercado Pago sem assinatura.');

    $order = '{"action":"merchant_order","type":"topic_merchant_order_wh","data":{"id":"777"},"id":5}';
    expect($this->gateway->parseWebhook($this->credentials, MercadoPagoHarness::sign('777', 'r', '1'), $order)->chargeId)->toBeNull();
});

it('recusa conta de fora do Brasil e reconhece erro nos dados de quem paga', function (): void {
    Http::fake([
        MP.'/users/me' => Http::response([...MercadoPagoHarness::fixture('users-me'), 'site_id' => 'MLA']),
        MP.'/v1/payments' => Http::response(MercadoPagoHarness::fixture('error-payer'), 400),
    ]);

    expect($this->gateway->verify($this->credentials))->valid->toBeFalse()->message->toBe('Esta conta do Mercado Pago não é do Brasil. Use uma conta brasileira para receber em reais.');

    try {
        $this->gateway->createPixCharge($this->credentials, $this->charge);
        $this->fail('Esperava GatewayRequestFailed.');
    } catch (GatewayRequestFailed $gatewayRequestFailed) {
        expect($gatewayRequestFailed->reason)->toBe(GatewayRequestFailed::INVALID_PAYER)->and($gatewayRequestFailed->httpStatus)->toBe(400);
    }
});

it('trocar do Asaas para o Mercado Pago numa edição sem vendas é só cadastrar a conta nova, com a chave pública do cartão', function (): void {
    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $organizer = User::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $payload = ['driver' => 'mercadopago', 'environment' => 'sandbox', 'api_key' => 'APP_USR-1234567890-chave-de-teste', 'webhook_secret' => MercadoPagoHarness::SECRET, 'holder_type' => 'individual', 'holder_name' => 'Fulana de Tal', 'holder_document' => '529.982.247-25', 'methods' => ['pix', 'card'], 'max_installments' => 6];
    $base = "/api/v1/editions/{$edition->id}/payments/accounts";

    $asaas = $this->postJson($base, [...$payload, 'driver' => 'asaas', 'api_key' => '$aact_hmlg_chave_de_teste'])->assertCreated()->json('data.id');

    $this->postJson($base, $payload)->assertStatus(422)->assertJsonPath('errors.public_key.0', 'Informe a chave pública: o formulário de cartão do gateway usa essa chave no navegador.');
    $this->postJson($base, [...$payload, 'public_key' => 'APP_USR-pub-0000-1111'])->assertCreated()->assertJsonPath('data.driver', 'mercadopago');

    $this->getJson($base)
        ->assertJsonPath('data.active.driver', 'mercadopago')
        ->assertJsonPath('data.previous.0.id', $asaas);

    $this->getJson("/api/v1/public/editions/{$edition->id}/payments/receiver")
        ->assertJsonPath('data.driver', 'mercadopago')
        ->assertJsonPath('data.public_key', 'APP_USR-pub-0000-1111');
});

it('contrato contra o sandbox do Mercado Pago: cria e consulta um Pix de teste', function (): void {
    $credentials = new GatewayCredentials((string) env('MERCADOPAGO_SANDBOX_ACCESS_TOKEN'), 'segredo', GatewayEnvironment::Sandbox);
    Http::allowStrayRequests();
    $this->travelBack();

    expect($this->gateway->verify($credentials)->valid)->toBeTrue();

    $charge = new ChargeRequest((string) Str::uuid(), 1_000, 'PHPeste · teste de contrato', new Payer('Teste PHPeste', 'test_user_phpeste@testuser.com', '52998224725'), now()->addMinutes(30));
    $pix = $this->gateway->createPixCharge($credentials, $charge);

    expect($pix->copyPaste)->toStartWith('000201')
        ->and($this->gateway->fetch($credentials, $pix->chargeId)->status)->toBe(ChargeStatus::Pending);

    $this->gateway->cancel($credentials, $pix->chargeId);
    expect($this->gateway->fetch($credentials, $pix->chargeId)->status)->toBe(ChargeStatus::Cancelled);
})->skip(fn (): bool => !is_string(env('MERCADOPAGO_SANDBOX_ACCESS_TOKEN')) || env('MERCADOPAGO_SANDBOX_ACCESS_TOKEN') === '', 'Defina MERCADOPAGO_SANDBOX_ACCESS_TOKEN para rodar contra o Mercado Pago com credenciais de teste.');
