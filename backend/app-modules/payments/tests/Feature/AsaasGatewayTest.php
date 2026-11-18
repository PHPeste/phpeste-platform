<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Drivers\Asaas\AsaasClient;
use PHPeste\Payments\Drivers\Asaas\AsaasGateway;
use PHPeste\Payments\Drivers\Asaas\AsaasStatus;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;

const SANDBOX = 'https://api-sandbox.asaas.com/v3';

function asaasFixture(string $name): array
{
    return json_decode((string) file_get_contents(__DIR__."/../Fixtures/asaas/{$name}.json"), true);
}

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->gateway = resolve(AsaasGateway::class);
    $this->credentials = new GatewayCredentials('$aact_hmlg_chave_de_teste', 'token-do-webhook', GatewayEnvironment::Sandbox);
    $this->charge = new ChargeRequest('01a1f07d-0000-7000-8000-000000000001', 15_540, 'PHPeste 2027 · pedido #1', new Payer('Ana Souza', 'ana@exemplo.com', '52998224725'), now()->addMinutes(30));
});

it('valida a chave lendo os dados comerciais, no ambiente certo e com os headers exigidos', function (): void {
    Http::fake([
        SANDBOX.'/myAccount/commercialInfo/' => Http::response(asaasFixture('commercial-info')),
        'https://api.asaas.com/v3/myAccount/commercialInfo/' => Http::response(asaasFixture('error-unauthorized'), 401),
    ]);

    $result = $this->gateway->verify($this->credentials);
    expect($result->valid)->toBeTrue()->and($result->accountName)->toBe('Fulana de Tal');

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('access_token', '$aact_hmlg_chave_de_teste')
        && $request->hasHeader('User-Agent', 'PHPeste-Platform')
        && str_starts_with($request->url(), SANDBOX));

    $production = $this->gateway->verify(new GatewayCredentials('$aact_prod_x', 's', GatewayEnvironment::Production));
    expect($production->valid)->toBeFalse()
        ->and($production->message)->toBe('O Asaas recusou a chave de API. Confira se ela é do ambiente Produção.');
});

it('cria o cliente e a cobrança Pix com QR e copia e cola', function (): void {
    Http::fake([
        SANDBOX.'/customers?*' => Http::response(asaasFixture('customers-empty')),
        SANDBOX.'/customers' => Http::response(asaasFixture('customer-created')),
        SANDBOX.'/payments' => Http::response(asaasFixture('payment-pix')),
        SANDBOX.'/payments/pay_080225913252/pixQrCode' => Http::response(asaasFixture('pix-qr')),
    ]);

    $pix = $this->gateway->createPixCharge($this->credentials, $this->charge);

    expect($pix->chargeId)->toBe('pay_080225913252')
        ->and($pix->copyPaste)->toStartWith('00020101021226820014br.gov.bcb.pix')
        ->and($pix->qrCodeBase64)->toStartWith('iVBORw0KGgo')
        ->and($pix->expiresAt->toIso8601ZuluString())->toBe('2027-06-10T12:30:00Z');

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->url() === SANDBOX.'/payments'
        && $request['customer'] === 'cus_000005219999'
        && $request['billingType'] === 'PIX'
        && $request['value'] === 155.4
        && $request['dueDate'] === '2027-06-10'
        && $request['externalReference'] === '01a1f07d-0000-7000-8000-000000000001');
    Http::assertSent(fn (Request $request): bool => $request->url() === SANDBOX.'/customers' && $request['cpfCnpj'] === '52998224725' && $request['notificationDisabled'] === true);
});

it('reaproveita o cliente já cadastrado e manda o cartão para a fatura do Asaas, com parcelas e retorno', function (): void {
    Http::fake([
        SANDBOX.'/customers?*' => Http::response(asaasFixture('customers-found')),
        SANDBOX.'/payments' => Http::response(asaasFixture('payment-card')),
    ]);

    $checkout = $this->gateway->createHostedCheckout($this->credentials, $this->charge, PaymentMethod::Card, 3, 'https://phpeste.php-pb.net/checkout/pedido/1');

    expect($checkout->chargeId)->toBe('pay_card_4n2ks8')->and($checkout->url)->toBe('https://sandbox.asaas.com/i/card4n2ks8');

    Http::assertSent(fn (Request $request): bool => $request->url() === SANDBOX.'/payments'
        && $request['billingType'] === 'CREDIT_CARD'
        && $request['installmentCount'] === 3
        && $request['totalValue'] === 155.4
        && $request['callback'] === ['successUrl' => 'https://phpeste.php-pb.net/checkout/pedido/1', 'autoRedirect' => true]);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === SANDBOX.'/customers' && $request->method() === 'POST');
    Http::assertNotSent(fn (Request $request): bool => isset($request['creditCard']) || isset($request['creditCardHolderInfo']) || isset($request['creditCardToken']));
});

it('mapeia os status do Asaas para os da plataforma', function (string $asaas, ChargeStatus $expected): void {
    expect(AsaasStatus::map($asaas))->toBe($expected);
})->with([
    ['PENDING', ChargeStatus::Pending],
    ['AWAITING_RISK_ANALYSIS', ChargeStatus::Pending],
    ['RECEIVED', ChargeStatus::Paid],
    ['CONFIRMED', ChargeStatus::Paid],
    ['RECEIVED_IN_CASH', ChargeStatus::Paid],
    ['REFUND_IN_PROGRESS', ChargeStatus::Paid],
    ['OVERDUE', ChargeStatus::Expired],
    ['REFUNDED', ChargeStatus::Refunded],
    ['CHARGEBACK_REQUESTED', ChargeStatus::Disputed],
    ['CHARGEBACK_DISPUTE', ChargeStatus::Disputed],
]);

it('consulta, exclui e estorna a cobrança', function (): void {
    Http::fake([
        SANDBOX.'/payments/pay_080225913252/refund' => Http::response(asaasFixture('payment-refunded')),
        SANDBOX.'/payments/pay_080225913252' => Http::sequence()->push(asaasFixture('payment-received'))->push(asaasFixture('payment-deleted')),
    ]);

    $snapshot = $this->gateway->fetch($this->credentials, 'pay_080225913252');
    expect($snapshot->status)->toBe(ChargeStatus::Paid)
        ->and($snapshot->amountCents)->toBe(15_540)
        ->and($snapshot->feeCents)->toBe(99)
        ->and($snapshot->paidAt?->toDateString())->toBe('2027-06-10');

    $this->gateway->cancel($this->credentials, 'pay_080225913252');
    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE' && $request->url() === SANDBOX.'/payments/pay_080225913252');

    $refund = $this->gateway->refund($this->credentials, 'pay_080225913252', 15_540);
    expect($refund->completed)->toBeTrue()->and($refund->refundId)->toBe('8c3c9d1a-8b26-4b6b-9b8e-6a0a21f6b001');
    Http::assertSent(fn (Request $request): bool => $request->url() === SANDBOX.'/payments/pay_080225913252/refund' && $request['value'] === 155.4);
});

it('valida o webhook pelo header asaas-access-token e lê o evento', function (): void {
    $body = (string) json_encode(asaasFixture('webhook-received'));

    $event = $this->gateway->parseWebhook($this->credentials, ['asaas-access-token' => 'token-do-webhook'], $body);

    expect($event->eventId)->toBe('evt_05b708f961d739ea7eba7e4db318f621&368604920')
        ->and($event->type)->toBe('PAYMENT_RECEIVED')
        ->and($event->chargeId)->toBe('pay_080225913252')
        ->and($event->status)->toBe(ChargeStatus::Paid)
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, ['asaas-access-token' => 'outro'], $body))->toThrow(InvalidWebhookSignature::class)
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, [], $body))->toThrow(InvalidWebhookSignature::class)
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, ['asaas-access-token' => 'token-do-webhook'], 'não é json'))->toThrow(InvalidWebhookSignature::class);
});

it('transforma erro do Asaas em erro tipado com mensagem segura para quem compra', function (): void {
    Http::fake([
        SANDBOX.'/customers?*' => Http::response(asaasFixture('customers-empty')),
        SANDBOX.'/customers' => Http::response(asaasFixture('error-cpf'), 400),
    ]);

    try {
        $this->gateway->createPixCharge($this->credentials, $this->charge);
        $this->fail('esperava erro');
    } catch (GatewayRequestFailed $gatewayRequestFailed) {
        expect($gatewayRequestFailed->reason)->toBe(GatewayRequestFailed::INVALID_PAYER)
            ->and($gatewayRequestFailed->httpStatus)->toBe(400)
            ->and($gatewayRequestFailed->getMessage())->toContain('O CPF/CNPJ informado é inválido.')
            ->and($gatewayRequestFailed->publicMessage())->toBe('O meio de pagamento não aceitou os dados de quem paga. Confira o nome, o e-mail e o CPF.')
            ->and($gatewayRequestFailed->publicMessage())->not->toContain('Asaas')->not->toContain('400');
    }
});

it('repete leitura que falhou por instabilidade, mas nunca repete a criação da cobrança', function (): void {
    Http::fake([
        SANDBOX.'/payments/pay_1' => Http::sequence()->push(['errors' => []], 502)->push(['errors' => []], 503)->push(asaasFixture('payment-received')),
        SANDBOX.'/customers?*' => Http::response(asaasFixture('customers-found')),
        SANDBOX.'/payments' => Http::response(['errors' => []], 500),
    ]);

    expect($this->gateway->fetch($this->credentials, 'pay_1')->status)->toBe(ChargeStatus::Paid);
    Http::assertSentCount(3);

    expect(fn () => $this->gateway->createPixCharge($this->credentials, $this->charge))
        ->toThrow(function (GatewayRequestFailed $exception): void {
            expect($exception->reason)->toBe(GatewayRequestFailed::UNAVAILABLE);
        });
    expect(collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === 'POST')->count())->toBe(1);
});

it('sem conexão com o Asaas, o erro diz que o meio de pagamento está fora do ar', function (): void {
    Http::fake(fn (): never => throw new ConnectionException('timeout'));

    expect(fn () => $this->gateway->fetch($this->credentials, 'pay_1'))
        ->toThrow(function (GatewayRequestFailed $exception): void {
            expect($exception->publicMessage())->toBe('O meio de pagamento está fora do ar agora. Tente de novo em alguns minutos; sua reserva continua valendo.');
        });
});

it('contrato contra o sandbox do Asaas: Pix e cartão aprovam e o Pix é estornado', function (): void {
    $credentials = new GatewayCredentials((string) env('ASAAS_SANDBOX_API_KEY'), 'token', GatewayEnvironment::Sandbox);
    Http::allowStrayRequests();
    $this->travelBack();

    expect($this->gateway->verify($credentials)->valid)->toBeTrue();

    $charge = new ChargeRequest((string) Str::uuid(), 1_000, 'PHPeste · teste de contrato', new Payer('Teste PHPeste', 'teste@phpeste.dev', '52998224725'), now()->addMinutes(30));
    $client = resolve(AsaasClient::class);

    $pix = $this->gateway->createPixCharge($credentials, $charge);
    expect($pix->copyPaste)->not->toBeEmpty();
    $client->post($credentials, "/sandbox/payment/{$pix->chargeId}/confirm", []);
    expect($this->gateway->fetch($credentials, $pix->chargeId)->status)->toBe(ChargeStatus::Paid);
    expect($this->gateway->refund($credentials, $pix->chargeId, 1_000)->amountCents)->toBe(1_000);

    $card = $this->gateway->createHostedCheckout($credentials, $charge, PaymentMethod::Card, 1, 'https://exemplo.dev/ok');
    expect($card->url)->toStartWith('https://');
    $client->post($credentials, "/sandbox/payment/{$card->chargeId}/confirm", []);
    expect($this->gateway->fetch($credentials, $card->chargeId)->status)->toBe(ChargeStatus::Paid);
})->skip(fn (): bool => !is_string(env('ASAAS_SANDBOX_API_KEY')) || env('ASAAS_SANDBOX_API_KEY') === '', 'Defina ASAAS_SANDBOX_API_KEY para rodar contra o sandbox do Asaas.');
