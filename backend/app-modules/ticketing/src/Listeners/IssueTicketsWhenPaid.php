<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Listeners;

use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\IssueTickets;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Events\OrderStatusChanged;
use PHPeste\Ticketing\Models\Order;

class IssueTicketsWhenPaid
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly IssueTickets $issue,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->to !== OrderStatus::Paid) {
            return;
        }

        $this->currentEdition->run($event->editionId, function () use ($event): void {
            $order = Order::query()->find($event->orderId);

            if ($order instanceof Order && $order->status === OrderStatus::Paid) {
                $this->issue->handle($order);
            }
        });
    }
}
