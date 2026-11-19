<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Data\ChargeRecord;
use PHPeste\Payments\Data\ChargeSnapshot;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Payments\Data\RefundRequest;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Payments\Events\RefundAccepted;
use PHPeste\Payments\Events\RefundFailed;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Payments\Models\PaymentRefund;

class PaymentLedgerService implements PaymentLedger
{
    public function __construct(
        private readonly GatewayRegistry $gateways,
        private readonly ChargeService $charges,
    ) {}

    public function charges(string $editionId): array
    {
        return array_values(PaymentCharge::query()->forEdition($editionId)
            ->whereIn('status', [ChargeStatus::Paid->value, ChargeStatus::Refunded->value, ChargeStatus::Disputed->value])->oldest()
            ->get()
            ->map(fn (PaymentCharge $charge): ChargeRecord => new ChargeRecord(
                $charge->id,
                $charge->reference,
                $charge->gateway_charge_id,
                $charge->method,
                $charge->status,
                $charge->amount_cents,
                $charge->refunded_cents,
                $charge->fee_cents,
                $charge->paid_at,
            ))
            ->all());
    }

    public function refresh(string $editionId, CarbonInterface $since): int
    {
        $charges = PaymentCharge::query()->forEdition($editionId)
            ->where(fn (Builder $query) => $query
                ->where('status', ChargeStatus::Pending->value)
                ->orWhere(fn (Builder $settled) => $settled->whereIn('status', [ChargeStatus::Paid->value, ChargeStatus::Disputed->value])->where('paid_at', '>=', $since))
                ->orWhere(fn (Builder $closed) => $closed->whereIn('status', [ChargeStatus::Cancelled->value, ChargeStatus::Expired->value])->where('updated_at', '>=', $since)))
            ->get();

        $checked = 0;

        foreach ($charges as $charge) {
            if ($this->charges->refresh($charge)) {
                $checked++;
            }
        }

        return $checked;
    }

    public function refund(RefundRequest $request): RefundRecord
    {
        if ($request->amountCents < 1) {
            throw ValidationException::withMessages(['amount_cents' => 'Informe um valor de reembolso maior que zero.']);
        }

        $charge = PaymentCharge::query()->forEdition($request->editionId)
            ->where('reference', $request->reference)
            ->where('status', ChargeStatus::Paid->value)
            ->latest('paid_at')
            ->first();

        if (!$charge instanceof PaymentCharge) {
            throw ValidationException::withMessages(['amount_cents' => 'Este pedido não tem pagamento confirmado para reembolsar.']);
        }

        $reserved = PaymentCharge::query()->forEdition($charge->edition_id)
            ->whereKey($charge->id)
            ->where('status', ChargeStatus::Paid->value)
            ->whereRaw('refunded_cents + ? <= amount_cents', [$request->amountCents])
            ->increment('refunded_cents', $request->amountCents, ['updated_at' => now()]);

        if ($reserved !== 1) {
            $available = $charge->amount_cents - (int) PaymentCharge::query()->forEdition($charge->edition_id)->whereKey($charge->id)->value('refunded_cents');

            throw ValidationException::withMessages(['amount_cents' => $available <= 0
                ? 'Este pagamento já foi reembolsado.'
                : sprintf('O valor passa do que ainda pode ser reembolsado: R$ %s.', number_format($available / 100, 2, ',', '.'))]);
        }

        $refund = PaymentRefund::create([
            'edition_id' => $charge->edition_id,
            'payment_charge_id' => $charge->id,
            'payment_account_id' => $charge->payment_account_id,
            'reference' => $charge->reference,
            'amount_cents' => $request->amountCents,
            'status' => RefundStatus::Unconfirmed,
            'reason' => $request->reason,
            'note' => $request->note,
            'metadata' => $request->metadata,
            'requested_by' => $request->actorId,
        ]);

        try {
            $result = $this->gateways->driver($charge->driver)->refund($this->charges->accountOf($charge)->gatewayCredentials(), $charge->gateway_charge_id, $request->amountCents);
        } catch (GatewayRequestFailed $gatewayRequestFailed) {
            Log::warning('payments.refund_failed', [
                'edition_id' => $charge->edition_id,
                'refund_id' => $refund->id,
                'charge_id' => $charge->id,
                'reason' => $gatewayRequestFailed->reason,
                'status' => $gatewayRequestFailed->httpStatus,
                'error' => $gatewayRequestFailed->getMessage(),
            ]);

            if ($gatewayRequestFailed->reason === GatewayRequestFailed::UNAVAILABLE) {
                $refund->update(['failure_reason' => 'O gateway não respondeu. Confira no painel do gateway se o estorno saiu e registre o resultado aqui.']);

                return $refund->refresh()->toRecord();
            }

            $this->fail($refund, 'O gateway recusou o estorno: '.mb_substr($gatewayRequestFailed->getMessage(), 0, 200), $request->actorId);

            throw ValidationException::withMessages(['amount_cents' => 'O gateway recusou o reembolso. Nada foi devolvido; confira o pagamento no painel do gateway.']);
        }

        $this->accept($refund, $result->completed, $result->refundId, $request->actorId);

        if ($result->completed && $charge->refresh()->refunded_cents >= $charge->amount_cents) {
            $this->charges->apply($charge, new ChargeSnapshot($charge->gateway_charge_id, ChargeStatus::Refunded, $charge->amount_cents, $charge->fee_cents));
        }

        return $refund->refresh()->toRecord();
    }

    public function refunds(string $editionId, ?string $reference = null): array
    {
        return array_values(PaymentRefund::query()->forEdition($editionId)
            ->when($reference !== null, fn ($query) => $query->where('reference', $reference))
            ->latest()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PaymentRefund $refund): RefundRecord => $refund->toRecord())
            ->all());
    }

    public function settleRefund(string $editionId, string $refundId, bool $completed, ?string $actorId): RefundRecord
    {
        $refund = PaymentRefund::query()->forEdition($editionId)->findOrFail($refundId);

        if ($refund->status !== RefundStatus::Unconfirmed) {
            throw ValidationException::withMessages(['refund' => 'Só reembolsos a conferir podem ser registrados manualmente.']);
        }

        if ($completed) {
            $this->accept($refund, true, null, $actorId);
            $charge = PaymentCharge::query()->forEdition($editionId)->findOrFail($refund->payment_charge_id);

            if ($charge->status === ChargeStatus::Paid && $charge->refunded_cents >= $charge->amount_cents) {
                $this->charges->apply($charge, new ChargeSnapshot($charge->gateway_charge_id, ChargeStatus::Refunded, $charge->amount_cents, $charge->fee_cents));
            }
        } else {
            $this->fail($refund, 'Registrado como não realizado pela organização.', $actorId);
        }

        return $refund->refresh()->toRecord();
    }

    private function accept(PaymentRefund $refund, bool $completed, ?string $gatewayRefundId, ?string $actorId): void
    {
        $accepted = PaymentRefund::query()->forEdition($refund->edition_id)
            ->whereKey($refund->id)
            ->where('status', RefundStatus::Unconfirmed->value)
            ->update([
                'status' => ($completed ? RefundStatus::Completed : RefundStatus::Pending)->value,
                'gateway_refund_id' => $gatewayRefundId ?? $refund->gateway_refund_id,
                'failure_reason' => null,
                'settled_by' => $actorId === $refund->requested_by ? null : $actorId,
                'accepted_at' => now(),
                'completed_at' => $completed ? now() : null,
                'updated_at' => now(),
            ]);

        if ($accepted === 1) {
            event(new RefundAccepted($refund->edition_id, $refund->id, $refund->reference, $refund->amount_cents, $refund->reason, $refund->note, $refund->metadata ?? [], $actorId ?? $refund->requested_by));
        }
    }

    private function fail(PaymentRefund $refund, string $failure, ?string $actorId): void
    {
        DB::transaction(function () use ($refund, $failure, $actorId): void {
            $failed = PaymentRefund::query()->forEdition($refund->edition_id)
                ->whereKey($refund->id)
                ->where('status', RefundStatus::Unconfirmed->value)
                ->update(['status' => RefundStatus::Failed->value, 'failure_reason' => $failure, 'settled_by' => $actorId, 'updated_at' => now()]);

            if ($failed !== 1) {
                return;
            }

            PaymentCharge::query()->forEdition($refund->edition_id)
                ->whereKey($refund->payment_charge_id)
                ->where('refunded_cents', '>=', $refund->amount_cents)
                ->decrement('refunded_cents', $refund->amount_cents, ['updated_at' => now()]);

            event(new RefundFailed($refund->edition_id, $refund->id, $refund->reference, $refund->amount_cents, $failure, $actorId));
        });
    }
}
