<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers;

use Illuminate\Support\Str;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\ChargeSnapshot;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\HostedCheckout;
use PHPeste\Payments\Data\PixCharge;
use PHPeste\Payments\Data\RefundResult;
use PHPeste\Payments\Data\VerificationResult;
use PHPeste\Payments\Data\WebhookEvent;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayCapability;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;

class FakeGateway implements PaymentGateway
{
    public const string SIGNATURE_HEADER = 'x-fake-signature';

    public const string DECLINED_TOKEN = 'tok_declined';

    /**
     * @var list<array{method: string, api_key: string, arguments: array<string, mixed>}>
     */
    public static array $calls = [];

    /**
     * @var array<string, array{amount: int, status: ChargeStatus, api_key: string}>
     */
    public static array $charges = [];

    public static function reset(): void
    {
        self::$calls = [];
        self::$charges = [];
    }

    public static function sign(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public static function pay(string $chargeId): void
    {
        self::$charges[$chargeId]['status'] = ChargeStatus::Paid;
    }

    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Gateway de teste';
    }

    public function capabilities(): array
    {
        return GatewayCapability::cases();
    }

    public function verify(GatewayCredentials $credentials): VerificationResult
    {
        $this->record('verify', $credentials, []);

        if (str_starts_with($credentials->apiKey, 'invalid')) {
            return new VerificationResult(false, 'O gateway recusou a chave de API.');
        }

        return new VerificationResult(true, null, 'Conta de teste');
    }

    public function createPixCharge(GatewayCredentials $credentials, ChargeRequest $charge): PixCharge
    {
        $this->record('createPixCharge', $credentials, ['reference' => $charge->reference, 'amount' => $charge->amountCents]);
        $id = 'pix_'.Str::lower(Str::random(10));
        self::$charges[$id] = ['amount' => $charge->amountCents, 'status' => ChargeStatus::Pending, 'api_key' => $credentials->apiKey];

        return new PixCharge($id, '00020126fake'.$id, base64_encode('qr:'.$id), $charge->expiresAt);
    }

    public function chargeCard(GatewayCredentials $credentials, ChargeRequest $charge, CardPayment $card): ChargeSnapshot
    {
        $this->record('chargeCard', $credentials, ['reference' => $charge->reference, 'amount' => $charge->amountCents, 'installments' => $card->installments]);
        $id = 'card_'.Str::lower(Str::random(10));
        $declined = $card->token === self::DECLINED_TOKEN;
        self::$charges[$id] = ['amount' => $charge->amountCents, 'status' => $declined ? ChargeStatus::Failed : ChargeStatus::Paid, 'api_key' => $credentials->apiKey];

        return new ChargeSnapshot($id, self::$charges[$id]['status'], $charge->amountCents, null, $declined ? null : now(), $declined ? 'Cartão recusado pelo emissor.' : null);
    }

    public function createHostedCheckout(GatewayCredentials $credentials, ChargeRequest $charge, PaymentMethod $method, int $installments, string $returnUrl): HostedCheckout
    {
        $this->record('createHostedCheckout', $credentials, ['reference' => $charge->reference, 'amount' => $charge->amountCents, 'method' => $method->value, 'installments' => $installments, 'return_url' => $returnUrl]);
        $id = 'inv_'.Str::lower(Str::random(10));
        self::$charges[$id] = ['amount' => $charge->amountCents, 'status' => ChargeStatus::Pending, 'api_key' => $credentials->apiKey];

        return new HostedCheckout($id, "https://fake-gateway.test/i/{$id}");
    }

    public function cancel(GatewayCredentials $credentials, string $chargeId): void
    {
        $charge = $this->charge($credentials, 'cancel', $chargeId);

        throw_if($charge['status'] !== ChargeStatus::Pending, GatewayRequestFailed::class, 'Só cobrança pendente pode ser cancelada.');

        self::$charges[$chargeId]['status'] = ChargeStatus::Cancelled;
    }

    public function fetch(GatewayCredentials $credentials, string $chargeId): ChargeSnapshot
    {
        $charge = $this->charge($credentials, 'fetch', $chargeId);

        return new ChargeSnapshot($chargeId, $charge['status'], $charge['amount']);
    }

    public function refund(GatewayCredentials $credentials, string $chargeId, int $amountCents): RefundResult
    {
        $charge = $this->charge($credentials, 'refund', $chargeId, ['amount' => $amountCents]);

        throw_if($charge['status'] !== ChargeStatus::Paid || $amountCents > $charge['amount'], GatewayRequestFailed::class, 'O gateway não aceitou o reembolso desta cobrança.');

        self::$charges[$chargeId]['status'] = ChargeStatus::Refunded;

        return new RefundResult('ref_'.Str::lower(Str::random(10)), $amountCents, true);
    }

    public function parseWebhook(GatewayCredentials $credentials, array $headers, string $body): WebhookEvent
    {
        $this->record('parseWebhook', $credentials, []);
        $signature = $headers[self::SIGNATURE_HEADER] ?? '';

        throw_if($credentials->webhookSecret === '' || !hash_equals(self::sign($body, $credentials->webhookSecret), $signature), InvalidWebhookSignature::class, 'Assinatura do webhook inválida.');

        /** @var array{id?: string, type?: string, charge_id?: string, status?: string} $payload */
        $payload = json_decode($body, true) ?: [];

        return new WebhookEvent(
            (string) ($payload['id'] ?? Str::uuid()),
            (string) ($payload['type'] ?? 'ping'),
            $payload['charge_id'] ?? null,
            isset($payload['status']) ? ChargeStatus::tryFrom($payload['status']) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{amount: int, status: ChargeStatus, api_key: string}
     */
    private function charge(GatewayCredentials $credentials, string $method, string $chargeId, array $arguments = []): array
    {
        $this->record($method, $credentials, ['charge_id' => $chargeId, ...$arguments]);
        $charge = self::$charges[$chargeId] ?? null;

        throw_if($charge === null || $charge['api_key'] !== $credentials->apiKey, GatewayRequestFailed::class, 'Cobrança não encontrada nesta conta do gateway.');

        return $charge;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function record(string $method, GatewayCredentials $credentials, array $arguments): void
    {
        self::$calls[] = ['method' => $method, 'api_key' => $credentials->apiKey, 'arguments' => $arguments];
    }
}
