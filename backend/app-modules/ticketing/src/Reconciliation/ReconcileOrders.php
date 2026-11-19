<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Reconciliation;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\Log;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Data\ChargeRecord;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Enums\ReconciliationKind;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\ReconciliationIssue;

class ReconcileOrders
{
    public const int LOOKBACK_DAYS = 7;

    public const int PENDING_REFUND_DAYS = 3;

    public function __construct(private readonly PaymentLedger $ledger) {}

    /**
     * @return array{refreshed: int, orders: int, open: int, new: int, resolved: int, fees: int}
     */
    public function handle(string $editionId): array
    {
        $refreshed = $this->ledger->refresh($editionId, now()->subDays(self::LOOKBACK_DAYS));
        $charges = collect($this->ledger->charges($editionId))->groupBy(fn (ChargeRecord $charge): string => $charge->reference);

        $orders = Order::query()->forEdition($editionId)
            ->where('total_cents', '>', 0)
            ->where(fn (Builder $query) => $query
                ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value])
                ->orWhereIn('id', $charges->keys()->all()))
            ->get();

        $found = [];
        $fees = 0;

        foreach ($orders as $order) {
            /** @var list<ChargeRecord> $settled */
            $settled = $charges->get($order->id, collect())->values()->all();
            $kept = array_values(array_filter($settled, fn (ChargeRecord $charge): bool => $charge->status !== ChargeStatus::Refunded && $charge->refundedCents < $charge->amountCents));

            if ($order->status === OrderStatus::Paid && $settled === []) {
                $found[] = $this->issue(ReconciliationKind::PaidWithoutCharge, $order, null, ['order_total_cents' => $order->total_cents]);
            }

            if (count($kept) > 1) {
                $found[] = $this->issue(ReconciliationKind::DuplicatePayment, $order, null, ['charges' => count($kept), 'order_total_cents' => $order->total_cents]);
            }

            foreach ($settled as $charge) {
                $details = ['charge_status' => $charge->status->value, 'charge_amount_cents' => $charge->amountCents, 'order_status' => $order->status->value, 'order_total_cents' => $order->total_cents];

                if ($charge->status === ChargeStatus::Disputed) {
                    $found[] = $this->issue(ReconciliationKind::Disputed, $order, $charge, $details);
                }

                if ($charge->status === ChargeStatus::Paid && $charge->refundedCents < $charge->amountCents && !in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                    $found[] = $this->issue(ReconciliationKind::ChargeWithoutOrder, $order, $charge, [...$details, 'refundable_cents' => $charge->amountCents - $charge->refundedCents]);
                }

                if ($order->status === OrderStatus::Paid && $charge->status === ChargeStatus::Paid && $charge->amountCents !== $order->total_cents) {
                    $found[] = $this->issue(ReconciliationKind::AmountMismatch, $order, $charge, $details);
                }
            }

            $fee = $kept[0]->feeCents ?? ($settled[0]->feeCents ?? null);

            if ($fee !== null && $fee !== $order->gateway_fee_cents && in_array($order->status, [OrderStatus::Paid, OrderStatus::Refunded], true)) {
                $order->update(['gateway_fee_cents' => $fee]);
                $fees++;
            }
        }

        foreach ($this->ledger->refunds($editionId) as $refund) {
            if ($this->unconfirmed($refund)) {
                $found[] = [
                    'kind' => ReconciliationKind::RefundUnconfirmed,
                    'fingerprint' => "refund_unconfirmed:{$refund->id}",
                    'order_id' => Order::query()->forEdition($editionId)->whereKey($refund->reference)->value('id'),
                    'payment_charge_id' => $refund->chargeId,
                    'details' => ['refund_id' => $refund->id, 'refund_status' => $refund->status->value, 'amount_cents' => $refund->amountCents, 'requested_at' => $refund->createdAt->toIso8601ZuluString()],
                ];
            }
        }

        return ['refreshed' => $refreshed, 'orders' => $orders->count(), ...$this->store($editionId, $found), 'fees' => $fees];
    }

    private function unconfirmed(RefundRecord $refund): bool
    {
        return $refund->status === RefundStatus::Unconfirmed
            || ($refund->status === RefundStatus::Pending && $refund->createdAt->lt(now()->subDays(self::PENDING_REFUND_DAYS)));
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{kind: ReconciliationKind, fingerprint: string, order_id: string, payment_charge_id: string|null, details: array<string, mixed>}
     */
    private function issue(ReconciliationKind $kind, Order $order, ?ChargeRecord $charge, array $details): array
    {
        return [
            'kind' => $kind,
            'fingerprint' => sprintf('%s:%s:%s', $kind->value, $order->id, $charge->id ?? '-'),
            'order_id' => $order->id,
            'payment_charge_id' => $charge?->id,
            'details' => ['order_number' => $order->number, ...$details],
        ];
    }

    /**
     * @param  list<array{kind: ReconciliationKind, fingerprint: string, order_id: string|null, payment_charge_id: string|null, details: array<string, mixed>}>  $found
     * @return array{open: int, new: int, resolved: int}
     */
    private function store(string $editionId, array $found): array
    {
        $new = 0;
        $seen = [];

        foreach ($found as $item) {
            $seen[] = $item['fingerprint'];
            $issue = ReconciliationIssue::query()->forEdition($editionId)->where('fingerprint', $item['fingerprint'])->first();

            if (!$issue instanceof ReconciliationIssue) {
                ReconciliationIssue::create([...$item, 'edition_id' => $editionId, 'first_seen_at' => now(), 'last_seen_at' => now()]);
                $this->alert($editionId, $item);
                $new++;

                continue;
            }

            $reopen = $issue->resolved_at !== null && $issue->resolved_by === null;
            $issue->update([
                'details' => $item['details'],
                'last_seen_at' => now(),
                ...($reopen ? ['resolved_at' => null, 'resolution' => null] : []),
            ]);

            if ($reopen) {
                $this->alert($editionId, $item);
                $new++;
            }
        }

        $resolved = ReconciliationIssue::query()->forEdition($editionId)
            ->whereNull('resolved_at')
            ->whereNotIn('fingerprint', $seen === [] ? [''] : $seen)
            ->update(['resolved_at' => now(), 'resolution' => 'A divergência não apareceu mais na conferência.', 'updated_at' => now()]);

        return [
            'open' => ReconciliationIssue::query()->forEdition($editionId)->whereNull('resolved_at')->count(),
            'new' => $new,
            'resolved' => $resolved,
        ];
    }

    /**
     * @param  array{kind: ReconciliationKind, fingerprint: string, order_id: string|null, payment_charge_id: string|null, details: array<string, mixed>}  $item
     */
    private function alert(string $editionId, array $item): void
    {
        Log::warning('ticketing.reconciliation_issue', [
            'edition_id' => $editionId,
            'kind' => $item['kind']->value,
            'order_id' => $item['order_id'],
            'charge_id' => $item['payment_charge_id'],
        ]);
    }
}
