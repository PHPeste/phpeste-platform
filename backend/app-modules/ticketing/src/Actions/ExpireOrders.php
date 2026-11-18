<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

class ExpireOrders
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function handle(): int
    {
        $expired = 0;

        $orders = Order::query()->withoutGlobalScopes()
            ->whereIn('status', [OrderStatus::Created->value, OrderStatus::AwaitingPayment->value])
            ->where('expires_at', '<=', now())
            ->oldest('expires_at')
            ->limit(500)
            ->get();

        foreach ($orders as $order) {
            try {
                $this->transition->handle($order, OrderStatus::Expired, null, 'reservation_expired');
                $expired++;
            } catch (ValidationException) {
                continue;
            }
        }

        return $expired;
    }
}
