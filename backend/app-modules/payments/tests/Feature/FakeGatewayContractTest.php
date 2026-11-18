<?php

declare(strict_types=1);

use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->gateway = new FakeGateway;
    $this->credentials = new GatewayCredentials('fake_key_1234', 'segredo', GatewayEnvironment::Sandbox);
    $this->charge = new ChargeRequest('pedido-1', 15_000, 'PHPeste 2027', new Payer('Ana', 'ana@exemplo.com', '52998224725'), now()->addMinutes(30));
});

it('implementa o contrato completo de gateway', function (): void {
    expect($this->gateway)->toBeInstanceOf(PaymentGateway::class)
        ->and($this->gateway->verify($this->credentials)->valid)->toBeTrue()
        ->and($this->gateway->verify(new GatewayCredentials('invalid_x', 's', GatewayEnvironment::Sandbox))->valid)->toBeFalse();

    $pix = $this->gateway->createPixCharge($this->credentials, $this->charge);
    expect($pix->copyPaste)->toStartWith('00020126')
        ->and($this->gateway->fetch($this->credentials, $pix->chargeId)->status)->toBe(ChargeStatus::Pending);

    $card = $this->gateway->chargeCard($this->credentials, $this->charge, new CardPayment('tok_ok', 3));
    $declined = $this->gateway->chargeCard($this->credentials, $this->charge, new CardPayment(FakeGateway::DECLINED_TOKEN, 1));
    expect($card->status)->toBe(ChargeStatus::Paid)
        ->and($declined->status)->toBe(ChargeStatus::Failed)
        ->and($declined->failureReason)->toBe('Cartão recusado pelo emissor.');

    expect($this->gateway->refund($this->credentials, $card->chargeId, 15_000)->completed)->toBeTrue()
        ->and($this->gateway->fetch($this->credentials, $card->chargeId)->status)->toBe(ChargeStatus::Refunded)
        ->and(fn () => $this->gateway->refund($this->credentials, $pix->chargeId, 15_000))->toThrow(GatewayRequestFailed::class)
        ->and(fn () => $this->gateway->fetch(new GatewayCredentials('outra_conta', 's', GatewayEnvironment::Sandbox), $card->chargeId))->toThrow(GatewayRequestFailed::class);

    $body = '{"id":"evt_9","type":"payment.confirmed","charge_id":"'.$pix->chargeId.'","status":"paid"}';
    $event = $this->gateway->parseWebhook($this->credentials, ['x-fake-signature' => FakeGateway::sign($body, 'segredo')], $body);
    expect($event->eventId)->toBe('evt_9')->and($event->status)->toBe(ChargeStatus::Paid)->and($event->chargeId)->toBe($pix->chargeId)
        ->and(fn () => $this->gateway->parseWebhook($this->credentials, ['x-fake-signature' => 'x'], $body))->toThrow(InvalidWebhookSignature::class);
});

it('credenciais não aparecem em dump nem em stack trace', function (): void {
    expect(print_r($this->credentials, true))->not->toContain('fake_key_1234')->not->toContain('segredo');
});
