<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\MercadoPago;

use Illuminate\Support\Facades\Date;
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

class MercadoPagoGateway implements PaymentGateway
{
    public const string SIGNATURE_HEADER = 'x-signature';

    public const string REQUEST_ID_HEADER = 'x-request-id';

    public function __construct(private readonly MercadoPagoClient $client) {}

    public function key(): string
    {
        return 'mercadopago';
    }

    public function label(): string
    {
        return 'Mercado Pago';
    }

    public function capabilities(): array
    {
        return [GatewayCapability::Pix, GatewayCapability::CardToken];
    }

    public function verify(GatewayCredentials $credentials): VerificationResult
    {
        try {
            $user = $this->client->get($credentials, '/users/me');
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            return new VerificationResult(false, match ($gatewayRequestFailed->reason) {
                GatewayRequestFailed::INVALID_CREDENTIALS => 'O Mercado Pago recusou o access token. Confira se você copiou o token de produção ou o de teste, conforme o ambiente escolhido.',
                GatewayRequestFailed::UNAVAILABLE => 'O Mercado Pago não respondeu. Tente de novo em alguns minutos.',
                default => 'O Mercado Pago não aceitou a verificação da conta.',
            });
        }

        if (($user['site_id'] ?? 'MLB') !== 'MLB') {
            return new VerificationResult(false, 'Esta conta do Mercado Pago não é do Brasil. Use uma conta brasileira para receber em reais.');
        }

        $name = mb_trim(sprintf('%s %s', is_string($user['first_name'] ?? null) ? $user['first_name'] : '', is_string($user['last_name'] ?? null) ? $user['last_name'] : ''));

        return new VerificationResult(true, null, $name !== '' ? $name : (is_string($user['nickname'] ?? null) ? $user['nickname'] : null));
    }

    public function createPixCharge(GatewayCredentials $credentials, ChargeRequest $charge): PixCharge
    {
        $payment = $this->client->post($credentials, '/v1/payments', [
            ...$this->payment($charge),
            'payment_method_id' => 'pix',
            'date_of_expiration' => $charge->expiresAt->clone()->setTimezone('America/Sao_Paulo')->format('Y-m-d\TH:i:s.vP'),
        ]);

        return new PixCharge(
            $this->id($payment),
            $this->nested($payment, ['point_of_interaction', 'transaction_data', 'qr_code']),
            $this->nested($payment, ['point_of_interaction', 'transaction_data', 'qr_code_base64']),
            $charge->expiresAt,
        );
    }

    public function chargeCard(GatewayCredentials $credentials, ChargeRequest $charge, CardPayment $card): ChargeSnapshot
    {
        throw_if($card->paymentMethodId === null, GatewayRequestFailed::class, 'O cartão chegou sem a bandeira escolhida no formulário do Mercado Pago.', GatewayRequestFailed::INVALID_PAYER);

        $payment = $this->client->post($credentials, '/v1/payments', array_filter([
            ...$this->payment($charge),
            'token' => $card->token,
            'installments' => $card->installments,
            'payment_method_id' => $card->paymentMethodId,
            'issuer_id' => $card->issuerId,
            'statement_descriptor' => mb_substr(Str::ascii($charge->description), 0, 13),
        ], fn (mixed $value): bool => $value !== null));

        return $this->snapshot($payment);
    }

    public function createHostedCheckout(GatewayCredentials $credentials, ChargeRequest $charge, PaymentMethod $method, int $installments, string $returnUrl): HostedCheckout
    {
        throw new GatewayRequestFailed('No Mercado Pago o cartão é digitado no formulário do próprio Mercado Pago dentro do checkout, sem página externa.', GatewayRequestFailed::REJECTED);
    }

    public function fetch(GatewayCredentials $credentials, string $chargeId): ChargeSnapshot
    {
        return $this->snapshot($this->client->get($credentials, "/v1/payments/{$chargeId}"));
    }

    public function cancel(GatewayCredentials $credentials, string $chargeId): void
    {
        $this->client->put($credentials, "/v1/payments/{$chargeId}", ['status' => 'cancelled']);
    }

    public function refund(GatewayCredentials $credentials, string $chargeId, int $amountCents): RefundResult
    {
        $refund = $this->client->post($credentials, "/v1/payments/{$chargeId}/refunds", ['amount' => $this->reais($amountCents)]);
        $status = is_string($refund['status'] ?? null) ? $refund['status'] : '';

        return new RefundResult($this->id($refund), $amountCents, $status === 'approved');
    }

    public function parseWebhook(GatewayCredentials $credentials, array $headers, string $body): WebhookEvent
    {
        $payload = json_decode($body, true);

        throw_if(!is_array($payload), InvalidWebhookSignature::class, 'Corpo do webhook do Mercado Pago inválido.');

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $dataId = is_scalar($data['id'] ?? null) ? mb_strtolower((string) $data['id']) : null;
        $this->verifySignature($credentials, $headers, $dataId);

        $type = is_string($payload['action'] ?? null) ? $payload['action'] : (is_string($payload['type'] ?? null) ? $payload['type'] : 'unknown');
        $isPayment = ($payload['type'] ?? null) === 'payment' || str_starts_with($type, 'payment.');
        $notificationId = is_scalar($payload['id'] ?? null) ? (string) $payload['id'] : null;

        return new WebhookEvent(
            $notificationId ?? hash('sha256', $body),
            $type,
            $isPayment ? $dataId : null,
            null,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function verifySignature(GatewayCredentials $credentials, array $headers, ?string $dataId): void
    {
        $parts = [];

        foreach (explode(',', $headers[self::SIGNATURE_HEADER] ?? '') as $part) {
            [$key, $value] = array_pad(explode('=', mb_trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        $ts = $parts['ts'] ?? '';
        $hash = $parts['v1'] ?? '';
        $requestId = $headers[self::REQUEST_ID_HEADER] ?? '';

        throw_if($credentials->webhookSecret === '' || $ts === '' || $hash === '', InvalidWebhookSignature::class, 'Webhook do Mercado Pago sem assinatura.');

        $manifest = ($dataId === null ? '' : "id:{$dataId};").($requestId === '' ? '' : "request-id:{$requestId};")."ts:{$ts};";

        throw_unless(hash_equals(hash_hmac('sha256', $manifest, $credentials->webhookSecret), $hash), InvalidWebhookSignature::class, 'Assinatura do webhook do Mercado Pago inválida.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(ChargeRequest $charge): array
    {
        [$first, $last] = array_pad(explode(' ', mb_trim($charge->payer->name), 2), 2, '');

        return [
            'transaction_amount' => $this->reais($charge->amountCents),
            'description' => mb_substr($charge->description, 0, 255),
            'external_reference' => $charge->reference,
            'payer' => array_filter([
                'email' => $charge->payer->email,
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : null,
                'identification' => $charge->payer->document === '' ? null : [
                    'type' => mb_strlen($charge->payer->document) === 14 ? 'CNPJ' : 'CPF',
                    'number' => $charge->payer->document,
                ],
            ], fn (mixed $value): bool => $value !== null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     */
    private function snapshot(array $payment): ChargeSnapshot
    {
        $detail = is_string($payment['status_detail'] ?? null) ? $payment['status_detail'] : '';
        $status = MercadoPagoStatus::map($this->string($payment, 'status'), $detail);
        $paidAt = $payment['date_approved'] ?? null;
        $fee = null;

        if (is_array($payment['fee_details'] ?? null) && $payment['fee_details'] !== []) {
            $fee = 0;

            foreach ($payment['fee_details'] as $item) {
                if (is_array($item) && ($item['fee_payer'] ?? 'collector') === 'collector') {
                    $fee += $this->cents($item['amount'] ?? 0);
                }
            }
        }

        return new ChargeSnapshot(
            $this->id($payment),
            $status,
            $this->cents($payment['transaction_amount'] ?? 0),
            $fee,
            is_string($paidAt) && $status === ChargeStatus::Paid ? Date::parse($paidAt) : null,
            $status === ChargeStatus::Failed ? MercadoPagoStatus::failure($detail) : null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function id(array $data): string
    {
        $id = $data['id'] ?? null;

        throw_unless(is_int($id) || (is_string($id) && $id !== ''), GatewayRequestFailed::class, 'O Mercado Pago respondeu sem o identificador.');

        return (string) $id;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        throw_unless(is_string($value) && $value !== '', GatewayRequestFailed::class, "O Mercado Pago respondeu sem {$key}.");

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $path
     */
    private function nested(array $data, array $path): string
    {
        $value = $data;

        foreach ($path as $key) {
            $value = is_array($value) ? ($value[$key] ?? null) : null;
        }

        throw_unless(is_string($value) && $value !== '', GatewayRequestFailed::class, sprintf('O Mercado Pago respondeu sem %s.', implode('.', $path)));

        return $value;
    }

    private function reais(int $cents): float
    {
        return round($cents / 100, 2);
    }

    private function cents(mixed $value): int
    {
        return is_numeric($value) ? (int) round(((float) $value) * 100) : 0;
    }
}
