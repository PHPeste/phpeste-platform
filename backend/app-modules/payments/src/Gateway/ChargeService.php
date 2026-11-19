<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\ChargeSnapshot;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Payments\Data\StartPayment;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\GatewayCapability;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Payments\Events\ChargeStatusChanged;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Payments\Models\PaymentRefund;

class ChargeService implements PaymentProcessor
{
    public const int REFRESH_SECONDS = 5;

    public function __construct(private readonly GatewayRegistry $gateways) {}

    public function start(StartPayment $payment): PaymentAttempt
    {
        $account = PaymentAccount::query()->forEdition($payment->editionId)->find($payment->accountId);

        if (!$account instanceof PaymentAccount || !$account->isReady()) {
            throw ValidationException::withMessages(['payment' => 'A conta que recebe os pagamentos desta edição mudou ou está pausada. Refaça o pedido.']);
        }

        $gateway = $this->gateways->driver($account->driver);
        $this->ensureSupported($account, $gateway, $payment);

        /** @var PaymentAttempt */
        return Cache::lock("payments:start:{$payment->reference}", 30)->block(20, fn (): PaymentAttempt => $this->create($account, $gateway, $payment));
    }

    public function current(string $editionId, string $reference, bool $refresh = false): ?PaymentAttempt
    {
        $charge = PaymentCharge::query()->forEdition($editionId)->where('reference', $reference)->latest()->orderByDesc('id')->first();

        if (!$charge instanceof PaymentCharge) {
            return null;
        }

        if ($refresh && !$charge->status->isFinal() && ($charge->checked_at === null || $charge->checked_at->lt(now()->subSeconds(self::REFRESH_SECONDS)))) {
            $this->refresh($charge);
        }

        return $charge->toAttempt();
    }

    public function cancelPending(string $editionId, string $reference): void
    {
        $pending = PaymentCharge::query()->forEdition($editionId)->where('reference', $reference)->where('status', ChargeStatus::Pending->value)->get();

        foreach ($pending as $charge) {
            $this->cancel($charge);
        }
    }

    public function sync(string $editionId, string $accountId, string $gatewayChargeId): ?ChargeStatus
    {
        $charge = PaymentCharge::query()->forEdition($editionId)
            ->where('payment_account_id', $accountId)
            ->where('gateway_charge_id', $gatewayChargeId)
            ->first();

        if (!$charge instanceof PaymentCharge) {
            return null;
        }

        $snapshot = $this->gateways->driver($charge->driver)->fetch($this->accountOf($charge)->gatewayCredentials(), $charge->gateway_charge_id);
        $this->apply($charge, $snapshot);

        return $charge->status;
    }

    public function refresh(PaymentCharge $charge): bool
    {
        try {
            $snapshot = $this->gateways->driver($charge->driver)->fetch($this->accountOf($charge)->gatewayCredentials(), $charge->gateway_charge_id);
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            Log::warning('payments.charge_refresh_failed', ['charge_id' => $charge->id, 'reason' => $gatewayRequestFailed->reason, 'error' => $gatewayRequestFailed->getMessage()]);
            $charge->update(['checked_at' => now()]);

            return false;
        }

        $this->apply($charge, $snapshot);

        return true;
    }

    public function apply(PaymentCharge $charge, ChargeSnapshot $snapshot): void
    {
        $from = $charge->status;
        $to = $snapshot->status;
        $values = ['checked_at' => now(), 'fee_cents' => $snapshot->feeCents ?? $charge->fee_cents];

        if ($from === $to) {
            $charge->update($values);

            return;
        }

        $changed = PaymentCharge::query()->forEdition($charge->edition_id)
            ->whereKey($charge->id)
            ->where('status', $from->value)
            ->update([
                ...$values,
                'status' => $to->value,
                'paid_at' => $to === ChargeStatus::Paid ? ($snapshot->paidAt ?? now()) : $charge->paid_at,
                'failure_reason' => $to === ChargeStatus::Failed ? new GatewayRequestFailed('', GatewayRequestFailed::DECLINED)->publicMessage() : $charge->failure_reason,
                'refunded_cents' => $to === ChargeStatus::Refunded ? $charge->amount_cents : $charge->refunded_cents,
            ]);

        $charge->refresh();

        if ($changed === 1 && $to === ChargeStatus::Refunded) {
            PaymentRefund::query()->forEdition($charge->edition_id)
                ->where('payment_charge_id', $charge->id)
                ->where('status', RefundStatus::Pending->value)
                ->update(['status' => RefundStatus::Completed->value, 'completed_at' => now(), 'updated_at' => now()]);
        }

        if ($changed === 1) {
            event(new ChargeStatusChanged($charge->edition_id, $charge->id, $charge->reference, $from, $to, $charge->amount_cents, $charge->fee_cents));
        }
    }

    public function accountOf(PaymentCharge $charge): PaymentAccount
    {
        return PaymentAccount::query()->forEdition($charge->edition_id)->findOrFail($charge->payment_account_id);
    }

    private function create(PaymentAccount $account, PaymentGateway $gateway, StartPayment $payment): PaymentAttempt
    {
        $existing = PaymentCharge::query()->forEdition($payment->editionId)
            ->where('reference', $payment->reference)
            ->where('status', ChargeStatus::Pending->value)
            ->get();

        foreach ($existing as $charge) {
            if ($charge->method === $payment->method && $charge->installments === $payment->installments && $charge->amount_cents === $payment->amountCents && $charge->payment_account_id === $account->id) {
                return $charge->toAttempt();
            }

            $this->cancel($charge);
        }

        $request = new ChargeRequest($payment->reference, $payment->amountCents, $payment->description, $payment->payer, $payment->expiresAt);
        $credentials = $account->gatewayCredentials();
        $attributes = [
            'edition_id' => $payment->editionId,
            'payment_account_id' => $account->id,
            'reference' => $payment->reference,
            'driver' => $account->driver,
            'method' => $payment->method,
            'amount_cents' => $payment->amountCents,
            'installments' => $payment->installments,
            'expires_at' => $payment->expiresAt,
            'checked_at' => now(),
        ];

        try {
            if ($payment->method === PaymentMethod::Pix) {
                $pix = $gateway->createPixCharge($credentials, $request);
                $attributes += ['gateway_charge_id' => $pix->chargeId, 'status' => ChargeStatus::Pending, 'pix_copy_paste' => $pix->copyPaste, 'pix_qr_code' => $pix->qrCodeBase64];
            } elseif ($payment->cardToken !== null && in_array(GatewayCapability::CardToken, $gateway->capabilities(), true)) {
                $snapshot = $gateway->chargeCard($credentials, $request, new CardPayment($payment->cardToken, $payment->installments, $payment->remoteIp));
                $attributes += [
                    'gateway_charge_id' => $snapshot->chargeId,
                    'status' => ChargeStatus::Pending,
                    'failure_reason' => $snapshot->status === ChargeStatus::Failed ? new GatewayRequestFailed('', GatewayRequestFailed::DECLINED)->publicMessage() : null,
                ];
            } else {
                $checkout = $gateway->createHostedCheckout($credentials, $request, $payment->method, $payment->installments, $payment->returnUrl);
                $attributes += ['gateway_charge_id' => $checkout->chargeId, 'status' => ChargeStatus::Pending, 'checkout_url' => $checkout->url];
            }
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            Log::warning('payments.charge_failed', [
                'edition_id' => $payment->editionId,
                'account_id' => $account->id,
                'reference' => $payment->reference,
                'method' => $payment->method->value,
                'reason' => $gatewayRequestFailed->reason,
                'status' => $gatewayRequestFailed->httpStatus,
                'error' => $gatewayRequestFailed->getMessage(),
            ]);

            throw ValidationException::withMessages(['payment' => $gatewayRequestFailed->publicMessage()]);
        }

        $charge = PaymentCharge::create($attributes);

        if (isset($snapshot)) {
            $this->apply($charge, $snapshot);
        }

        return $charge->refresh()->toAttempt();
    }

    private function cancel(PaymentCharge $charge): void
    {
        try {
            $this->gateways->driver($charge->driver)->cancel($this->accountOf($charge)->gatewayCredentials(), $charge->gateway_charge_id);
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            Log::warning('payments.charge_cancel_failed', ['charge_id' => $charge->id, 'reason' => $gatewayRequestFailed->reason, 'error' => $gatewayRequestFailed->getMessage()]);
            $this->refresh($charge);

            return;
        }

        $this->apply($charge, new ChargeSnapshot($charge->gateway_charge_id, ChargeStatus::Cancelled, $charge->amount_cents));
    }

    private function ensureSupported(PaymentAccount $account, PaymentGateway $gateway, StartPayment $payment): void
    {
        $capabilities = $gateway->capabilities();
        $supported = match ($payment->method) {
            PaymentMethod::Pix => in_array(GatewayCapability::Pix, $capabilities, true),
            PaymentMethod::Card => in_array(GatewayCapability::HostedCard, $capabilities, true) || ($payment->cardToken !== null && in_array(GatewayCapability::CardToken, $capabilities, true)),
            PaymentMethod::Boleto => in_array(GatewayCapability::HostedBoleto, $capabilities, true),
        };

        if (!$supported || !in_array($payment->method, $account->paymentMethods(), true)) {
            throw ValidationException::withMessages(['method' => sprintf('%s não está disponível nesta edição.', $payment->method->label())]);
        }

        if ($payment->installments < 1 || $payment->installments > ($payment->method === PaymentMethod::Card ? $account->max_installments : 1)) {
            throw ValidationException::withMessages(['installments' => $payment->method === PaymentMethod::Card
                ? sprintf('Escolha de 1 a %d parcelas.', $account->max_installments)
                : 'Só cartão pode ser parcelado.']);
        }
    }
}
