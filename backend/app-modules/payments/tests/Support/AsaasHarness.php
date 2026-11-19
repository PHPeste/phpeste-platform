<?php

declare(strict_types=1);

namespace PHPeste\Payments\Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Drivers\Asaas\AsaasGateway;
use PHPeste\Payments\Enums\GatewayEnvironment;

final class AsaasHarness implements GatewayHarness
{
    public const string URL = 'https://api-sandbox.asaas.com/v3';

    public static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__."/../Fixtures/asaas/{$name}.json"), true);
    }

    public function gateway(): PaymentGateway
    {
        return resolve(AsaasGateway::class);
    }

    public function credentials(): GatewayCredentials
    {
        return new GatewayCredentials('$aact_hmlg_chave_de_teste', 'token-do-webhook', GatewayEnvironment::Sandbox);
    }

    public function invalidCredentials(): GatewayCredentials
    {
        return new GatewayCredentials('$aact_hmlg_invalida', 'token-do-webhook', GatewayEnvironment::Sandbox);
    }

    public function stubAccount(): void
    {
        Http::fake(fn (Request $request) => $request->hasHeader('access_token', '$aact_hmlg_invalida')
            ? Http::response(self::fixture('error-unauthorized'), 401)
            : Http::response(self::fixture('commercial-info')));
    }

    public function stubPixCreated(): string
    {
        Http::fake([
            self::URL.'/customers?*' => Http::response(self::fixture('customers-empty')),
            self::URL.'/customers' => Http::response(self::fixture('customer-created')),
            self::URL.'/payments' => Http::response(self::fixture('payment-pix')),
            self::URL.'/payments/pay_080225913252/pixQrCode' => Http::response(self::fixture('pix-qr')),
        ]);

        return 'pay_080225913252';
    }

    public function stubPaid(string $chargeId): int
    {
        Http::fake([self::URL."/payments/{$chargeId}" => Http::response(self::fixture('payment-received'))]);

        return 99;
    }

    public function stubRefunded(string $chargeId): void
    {
        Http::fake([self::URL."/payments/{$chargeId}/refund" => Http::response(self::fixture('payment-refunded'))]);
    }

    public function stubCancelled(string $chargeId): void
    {
        Http::fake([self::URL."/payments/{$chargeId}" => Http::response(self::fixture('payment-deleted'))]);
    }

    public function stubOutage(string $chargeId): void
    {
        Http::fake([self::URL.'/*' => Http::response(['errors' => []], 503)]);
    }

    public function signedWebhook(): array
    {
        return ['headers' => [AsaasGateway::WEBHOOK_HEADER => 'token-do-webhook'], 'body' => (string) json_encode(self::fixture('webhook-received')), 'charge_id' => 'pay_080225913252'];
    }

    public function sentAmount(): float
    {
        $value = null;

        Http::assertSent(function (Request $request) use (&$value): bool {
            if ($request->method() === 'POST' && isset($request['value'])) {
                $value = $request['value'];
            }

            return true;
        });

        return (float) $value;
    }
}
