<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Data\Allocation;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Events\OrderStatusChanged;
use PHPeste\Ticketing\Models\CouponRedemption;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;

class TransitionOrder
{
    public function __construct(
        private readonly ReleaseTickets $releaseTickets,
        private readonly ReleaseCouponRedemption $releaseCoupon,
    ) {}

    public function handle(Order $order, OrderStatus $to, ?string $actorId = null, ?string $reason = null): Order
    {
        $from = $order->status;

        if (!$from->canBecome($to)) {
            throw ValidationException::withMessages(['status' => sprintf('Um pedido %s não pode passar para %s.', mb_strtolower($from->label()), mb_strtolower($to->label()))]);
        }

        DB::transaction(function () use ($order, $from, $to, $actorId, $reason): void {
            $column = $to->timestampColumn();
            $changed = Order::query()->forEdition($order->edition_id)
                ->whereKey($order->id)
                ->where('status', $from->value)
                ->update(['status' => $to->value, ...($column === null ? [] : [$column => now()])]);

            if ($changed === 0) {
                throw ValidationException::withMessages(['status' => 'O pedido mudou enquanto isso. Atualize e tente de novo.']);
            }

            if ($from->holdsInventory() && !$to->holdsInventory() && $to !== OrderStatus::Refunded) {
                $this->release($order);
            }

            event(new OrderStatusChanged($order->edition_id, $order->id, $from, $to, $actorId, $reason));
        });

        return $order->refresh();
    }

    private function release(Order $order): void
    {
        $items = OrderItem::query()->forEdition($order->edition_id)->where('order_id', $order->id)->get();

        foreach ($items->groupBy('ticket_batch_id') as $batchId => $group) {
            $first = $group->first();

            if ($first instanceof OrderItem) {
                $this->releaseTickets->handle(new Allocation($order->edition_id, $first->ticket_type_id, (string) $batchId, $first->batch_name, $group->count(), $first->unit_price_cents));
            }
        }

        if ($order->coupon_redemption_id !== null) {
            $redemption = CouponRedemption::query()->forEdition($order->edition_id)->find($order->coupon_redemption_id);

            if ($redemption instanceof CouponRedemption) {
                $this->releaseCoupon->handle($redemption);
            }
        }
    }
}
