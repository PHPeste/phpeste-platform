<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Listeners;

use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Events\OrderStatusChanged;

class CancelPendingCharges
{
    public function __construct(private readonly PaymentProcessor $payments) {}

    public function handle(OrderStatusChanged $event): void
    {
        if (in_array($event->to, [OrderStatus::Expired, OrderStatus::Cancelled], true)) {
            $this->payments->cancelPending($event->editionId, $event->orderId);
        }
    }
}
