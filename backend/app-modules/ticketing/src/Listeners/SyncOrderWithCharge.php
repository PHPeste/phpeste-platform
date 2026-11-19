<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Events\ChargeStatusChanged;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

class SyncOrderWithCharge
{
    public function __construct(
        private readonly TransitionOrder $transition,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function handle(ChargeStatusChanged $event): void
    {
        $target = match ($event->to) {
            ChargeStatus::Paid => OrderStatus::Paid,
            ChargeStatus::Refunded => OrderStatus::Refunded,
            default => null,
        };

        if ($target === null) {
            return;
        }

        $this->currentEdition->run($event->editionId, function () use ($event, $target): void {
            $order = Order::query()->find($event->reference);

            if (!$order instanceof Order || $order->status === $target) {
                return;
            }

            if (!$order->status->canBecome($target) || ($target === OrderStatus::Paid && $event->amountCents !== $order->total_cents)) {
                Log::warning('ticketing.charge_without_order_transition', [
                    'edition_id' => $event->editionId,
                    'order_id' => $order->id,
                    'order_status' => $order->status->value,
                    'charge_id' => $event->chargeId,
                    'charge_status' => $event->to->value,
                ]);

                return;
            }

            try {
                $this->transition->handle($order, $target, null, "charge:{$event->chargeId}");
            } catch (ValidationException) {
                Log::info('ticketing.order_already_moved', ['order_id' => $order->id, 'charge_id' => $event->chargeId]);
            }
        });
    }
}
