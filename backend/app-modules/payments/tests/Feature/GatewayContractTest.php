<?php

declare(strict_types=1);

use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Data\WebhookEvent;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayCapability;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;
use PHPeste\Payments\Tests\Support\AsaasHarness;
use PHPeste\Payments\Tests\Support\GatewayHarness;
use PHPeste\Payments\Tests\Support\MercadoPagoHarness;

dataset('drivers', [
    'asaas' => [fn (): GatewayHarness => new AsaasHarness],
    'mercadopago' => [fn (): GatewayHarness => new MercadoPagoHarness],
]);

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->charge = new ChargeRequest('01a1f07d-0000-7000-8000-000000000001', 15_540, 'PHPeste 2027 · pedido #1', new Payer('Ana Souza', 'ana@exemplo.com', '52998224725'), now()->addMinutes(30));
});

it('fica registrado e declara Pix e uma forma de cartão', function (GatewayHarness $harness): void {
    $gateway = $harness->gateway();
    $capabilities = $gateway->capabilities();

    expect(resolve(GatewayRegistry::class)->driver($gateway->key()))->toBeInstanceOf($gateway::class)
        ->and($gateway->label())->not->toBe('')
        ->and($capabilities)->toContain(GatewayCapability::Pix)
        ->and(in_array(GatewayCapability::HostedCard, $capabilities, true) || in_array(GatewayCapability::CardToken, $capabilities, true))->toBeTrue();
})->with('drivers');

it('valida as credenciais e explica quando o gateway recusa', function (GatewayHarness $harness): void {
    $harness->stubAccount();

    $valid = $harness->gateway()->verify($harness->credentials());
    $invalid = $harness->gateway()->verify($harness->invalidCredentials());

    expect($valid->valid)->toBeTrue()
        ->and($valid->accountName)->toBe('Fulana de Tal')
        ->and($invalid->valid)->toBeFalse()
        ->and($invalid->message)->toContain($harness->gateway()->label());
})->with('drivers');

it('cria Pix com copia e cola, QR e o vencimento da reserva, cobrando o valor em reais', function (GatewayHarness $harness): void {
    $id = $harness->stubPixCreated();

    $pix = $harness->gateway()->createPixCharge($harness->credentials(), $this->charge);

    expect($pix->chargeId)->toBe($id)
        ->and($pix->copyPaste)->toStartWith('000201')
        ->and($pix->qrCodeBase64)->toStartWith('iVBORw0KGgo')
        ->and($pix->expiresAt->toIso8601ZuluString())->toBe('2027-06-10T12:30:00Z')
        ->and($harness->sentAmount())->toBe(155.4);
})->with('drivers');

it('consulta a cobrança paga com valor, taxa e data do pagamento', function (GatewayHarness $harness): void {
    $id = $harness->stubPixCreated();
    $fee = $harness->stubPaid($id);

    $snapshot = $harness->gateway()->fetch($harness->credentials(), $id);

    expect($snapshot->chargeId)->toBe($id)
        ->and($snapshot->status)->toBe(ChargeStatus::Paid)
        ->and($snapshot->amountCents)->toBe(15_540)
        ->and($snapshot->feeCents)->toBe($fee)
        ->and($snapshot->paidAt)->not->toBeNull();
})->with('drivers');

it('estorna e cancela pela API do gateway', function (GatewayHarness $harness): void {
    $harness->stubRefunded('1325467890');
    $harness->stubRefunded('pay_080225913252');

    $refund = $harness->gateway()->refund($harness->credentials(), $harness instanceof AsaasHarness ? 'pay_080225913252' : '1325467890', 15_540);

    expect($refund->amountCents)->toBe(15_540)
        ->and($refund->completed)->toBeTrue()
        ->and($refund->refundId)->not->toBe('')
        ->and($harness->sentAmount())->toBe(155.4);

    $harness->stubCancelled('pay_x');
    $harness->stubCancelled('1325467000');
    $harness->gateway()->cancel($harness->credentials(), $harness instanceof AsaasHarness ? 'pay_x' : '1325467000');
})->with('drivers');

it('aceita só webhook assinado e diz qual cobrança mudou', function (GatewayHarness $harness): void {
    $webhook = $harness->signedWebhook();

    $event = $harness->gateway()->parseWebhook($harness->credentials(), $webhook['headers'], $webhook['body']);

    expect($event->chargeId)->toBe($webhook['charge_id'])
        ->and($event->eventId)->not->toBe('')
        ->and(fn (): WebhookEvent => $harness->gateway()->parseWebhook($harness->credentials(), [], $webhook['body']))->toThrow(InvalidWebhookSignature::class)
        ->and(fn (): WebhookEvent => $harness->gateway()->parseWebhook(new GatewayCredentials($harness->credentials()->apiKey, 'outro-segredo-qualquer', GatewayEnvironment::Sandbox), $webhook['headers'], $webhook['body']))->toThrow(InvalidWebhookSignature::class);
})->with('drivers');

it('gateway fora do ar vira erro tipado, com o motivo certo', function (GatewayHarness $harness): void {
    $harness->stubOutage('x');

    try {
        $harness->gateway()->fetch($harness->credentials(), 'x');
        $this->fail('Esperava GatewayRequestFailed.');
    } catch (GatewayRequestFailed $gatewayRequestFailed) {
        expect($gatewayRequestFailed->reason)->toBe(GatewayRequestFailed::UNAVAILABLE)
            ->and($gatewayRequestFailed->publicMessage())->not->toContain($harness->gateway()->label());
    }
})->with('drivers');
