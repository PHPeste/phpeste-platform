<?php

declare(strict_types=1);

namespace PHPeste\Payments\Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Drivers\MercadoPago\MercadoPagoGateway;
use PHPeste\Payments\Enums\GatewayEnvironment;

final class MercadoPagoHarness implements GatewayHarness
{
    public const string URL = 'https://api.mercadopago.com';

    public const string SECRET = 'segredo-da-assinatura-mp';

    /**
     * @return array<string, string>
     */
    public static function sign(string $dataId, string $requestId, string $ts, string $secret = self::SECRET): array
    {
        $hash = hash_hmac('sha256', "id:{$dataId};request-id:{$requestId};ts:{$ts};", $secret);

        return [MercadoPagoGateway::SIGNATURE_HEADER => "ts={$ts},v1={$hash}", MercadoPagoGateway::REQUEST_ID_HEADER => $requestId];
    }

    public static function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__."/../Fixtures/mercadopago/{$name}.json"), true);
    }

    public function gateway(): PaymentGateway
    {
        return resolve(MercadoPagoGateway::class);
    }

    public function credentials(): GatewayCredentials
    {
        return new GatewayCredentials('APP_USR-1234567890-chave-de-teste', self::SECRET, GatewayEnvironment::Sandbox);
    }

    public function invalidCredentials(): GatewayCredentials
    {
        return new GatewayCredentials('APP_USR-invalida', self::SECRET, GatewayEnvironment::Sandbox);
    }

    public function stubAccount(): void
    {
        Http::fake(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer APP_USR-invalida')
            ? Http::response(self::fixture('error-unauthorized'), 401)
            : Http::response(self::fixture('users-me')));
    }

    public function stubPixCreated(): string
    {
        Http::fake([self::URL.'/v1/payments' => Http::response(self::fixture('payment-pix'), 201)]);

        return '1325467890';
    }

    public function stubPaid(string $chargeId): int
    {
        Http::fake([self::URL."/v1/payments/{$chargeId}" => Http::response(self::fixture('payment-approved'))]);

        return 154;
    }

    public function stubRefunded(string $chargeId): void
    {
        Http::fake([self::URL."/v1/payments/{$chargeId}/refunds" => Http::response(self::fixture('refund'), 201)]);
    }

    public function stubCancelled(string $chargeId): void
    {
        Http::fake([self::URL."/v1/payments/{$chargeId}" => Http::response(self::fixture('payment-cancelled'))]);
    }

    public function stubOutage(string $chargeId): void
    {
        Http::fake([self::URL.'/*' => Http::response(['message' => 'internal_error'], 503)]);
    }

    public function signedWebhook(): array
    {
        $body = (string) json_encode(self::fixture('webhook-payment'));

        return ['headers' => self::sign('1325467890', 'req-abc-123', '1781089513'), 'body' => $body, 'charge_id' => '1325467890'];
    }

    public function sentAmount(): float
    {
        $value = null;

        Http::assertSent(function (Request $request) use (&$value): bool {
            if ($request->method() === 'POST') {
                $value = $request['transaction_amount'] ?? $request['amount'] ?? $value;
            }

            return true;
        });

        return (float) $value;
    }
}
