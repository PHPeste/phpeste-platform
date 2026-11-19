<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\Asaas;

use Illuminate\Support\Facades\Date;
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

class AsaasGateway implements PaymentGateway
{
    public const string WEBHOOK_HEADER = 'asaas-access-token';

    public function __construct(private readonly AsaasClient $client) {}

    public function key(): string
    {
        return 'asaas';
    }

    public function label(): string
    {
        return 'Asaas';
    }

    public function capabilities(): array
    {
        return [GatewayCapability::Pix, GatewayCapability::HostedCard, GatewayCapability::HostedBoleto];
    }

    public function verify(GatewayCredentials $credentials): VerificationResult
    {
        try {
            $info = $this->client->get($credentials, '/myAccount/commercialInfo/');
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            return new VerificationResult(false, match ($gatewayRequestFailed->reason) {
                GatewayRequestFailed::INVALID_CREDENTIALS => sprintf('O Asaas recusou a chave de API. Confira se ela é do ambiente %s.', $credentials->environment->label()),
                GatewayRequestFailed::UNAVAILABLE => 'O Asaas não respondeu. Tente de novo em alguns minutos.',
                default => 'O Asaas não aceitou a verificação da conta.',
            });
        }

        $name = $info['companyName'] ?? $info['name'] ?? null;

        return new VerificationResult(true, null, is_string($name) ? $name : null);
    }

    public function createPixCharge(GatewayCredentials $credentials, ChargeRequest $charge): PixCharge
    {
        $payment = $this->createPayment($credentials, $charge, 'PIX');
        $id = $this->string($payment, 'id');
        $qr = $this->client->get($credentials, "/payments/{$id}/pixQrCode");

        return new PixCharge($id, $this->string($qr, 'payload'), $this->string($qr, 'encodedImage'), $charge->expiresAt);
    }

    public function chargeCard(GatewayCredentials $credentials, ChargeRequest $charge, CardPayment $card): ChargeSnapshot
    {
        throw new GatewayRequestFailed('O Asaas não tokeniza cartão no navegador; o cartão é pago na fatura do Asaas.', GatewayRequestFailed::REJECTED);
    }

    public function createHostedCheckout(GatewayCredentials $credentials, ChargeRequest $charge, PaymentMethod $method, int $installments, string $returnUrl): HostedCheckout
    {
        $extra = ['callback' => ['successUrl' => $returnUrl, 'autoRedirect' => true]];

        if ($method === PaymentMethod::Card && $installments > 1) {
            $extra['installmentCount'] = $installments;
            $extra['totalValue'] = $this->reais($charge->amountCents);
        }

        $payment = $this->createPayment($credentials, $charge, $method === PaymentMethod::Boleto ? 'BOLETO' : 'CREDIT_CARD', $extra);

        return new HostedCheckout($this->string($payment, 'id'), $this->string($payment, 'invoiceUrl'));
    }

    public function fetch(GatewayCredentials $credentials, string $chargeId): ChargeSnapshot
    {
        $payment = $this->client->get($credentials, "/payments/{$chargeId}");
        $status = AsaasStatus::map($this->string($payment, 'status'), ($payment['deleted'] ?? false) === true);
        $value = $this->cents($payment['value'] ?? 0);
        $net = isset($payment['netValue']) ? $this->cents($payment['netValue']) : null;
        $paidAt = $payment['confirmedDate'] ?? $payment['paymentDate'] ?? null;

        return new ChargeSnapshot(
            $chargeId,
            $status,
            $value,
            $net === null ? null : max(0, $value - $net),
            is_string($paidAt) && $status === ChargeStatus::Paid ? Date::parse($paidAt) : null,
        );
    }

    public function cancel(GatewayCredentials $credentials, string $chargeId): void
    {
        $this->client->delete($credentials, "/payments/{$chargeId}");
    }

    public function refund(GatewayCredentials $credentials, string $chargeId, int $amountCents): RefundResult
    {
        $payment = $this->client->post($credentials, "/payments/{$chargeId}/refund", ['value' => $this->reais($amountCents)]);
        $refunds = is_array($payment['refunds'] ?? null) ? $payment['refunds'] : [];
        $last = end($refunds);
        $refundId = is_array($last) && is_string($last['id'] ?? null) ? $last['id'] : $chargeId;

        return new RefundResult($refundId, $amountCents, AsaasStatus::map($this->string($payment, 'status')) === ChargeStatus::Refunded);
    }

    public function parseWebhook(GatewayCredentials $credentials, array $headers, string $body): WebhookEvent
    {
        $token = $headers[self::WEBHOOK_HEADER] ?? '';

        throw_if($credentials->webhookSecret === '' || !hash_equals($credentials->webhookSecret, $token), InvalidWebhookSignature::class, 'Token do webhook do Asaas inválido.');

        $payload = json_decode($body, true);

        throw_if(!is_array($payload) || !is_string($payload['event'] ?? null), InvalidWebhookSignature::class, 'Corpo do webhook do Asaas inválido.');

        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $type = $payload['event'];
        $status = AsaasStatus::fromEvent($type) ?? (is_string($payment['status'] ?? null) ? AsaasStatus::map($payment['status']) : null);

        return new WebhookEvent(
            is_string($payload['id'] ?? null) ? $payload['id'] : hash('sha256', $body),
            $type,
            is_string($payment['id'] ?? null) ? $payment['id'] : null,
            $status,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function createPayment(GatewayCredentials $credentials, ChargeRequest $charge, string $billingType, array $extra = []): array
    {
        return $this->client->post($credentials, '/payments', [
            'customer' => $this->customer($credentials, $charge),
            'billingType' => $billingType,
            'value' => $this->reais($charge->amountCents),
            'dueDate' => $charge->expiresAt->clone()->setTimezone('America/Sao_Paulo')->toDateString(),
            'description' => mb_substr($charge->description, 0, 500),
            'externalReference' => $charge->reference,
            ...$extra,
        ]);
    }

    private function customer(GatewayCredentials $credentials, ChargeRequest $charge): string
    {
        $found = $this->client->get($credentials, '/customers', ['cpfCnpj' => $charge->payer->document, 'limit' => 1]);
        $data = is_array($found['data'] ?? null) ? $found['data'] : [];
        $first = $data[0] ?? null;

        if (is_array($first) && is_string($first['id'] ?? null)) {
            return $first['id'];
        }

        $created = $this->client->post($credentials, '/customers', [
            'name' => $charge->payer->name,
            'cpfCnpj' => $charge->payer->document,
            'email' => $charge->payer->email,
            'notificationDisabled' => true,
        ]);

        return $this->string($created, 'id');
    }

    private function reais(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function cents(mixed $value): int
    {
        return is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function string(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        throw_if(!is_string($value) || $value === '', GatewayRequestFailed::class, "Resposta do Asaas sem o campo {$key}.", GatewayRequestFailed::UNAVAILABLE);

        return $value;
    }
}
