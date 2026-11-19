<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use PHPeste\Ticketing\Contracts\TicketRevenue;
use PHPeste\Ticketing\Data\TicketRevenueLine;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketingSetting;

class TicketRevenueReport implements TicketRevenue
{
    public function byAccount(string $editionId): array
    {
        $basisPoints = (int) (TicketingSetting::query()->forEdition($editionId)->value('gateway_fee_basis_points') ?? 0);
        $lines = [];

        $orders = Order::query()->forEdition($editionId)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value])
            ->where('total_cents', '>', 0)
            ->orderBy('number')
            ->get(['id', 'payment_account_id', 'total_cents', 'refunded_cents', 'gateway_fee_cents']);

        foreach ($orders->groupBy(fn (Order $order): string => $order->payment_account_id ?? '') as $accountId => $group) {
            $fees = 0;
            $estimated = 0;

            foreach ($group as $order) {
                if ($order->gateway_fee_cents === null) {
                    $estimated++;
                    $fees += Split::fee($order->total_cents, $basisPoints);
                } else {
                    $fees += $order->gateway_fee_cents;
                }
            }

            $lines[] = new TicketRevenueLine(
                $accountId === '' ? null : (string) $accountId,
                $group->count(),
                (int) $group->sum('total_cents'),
                (int) $group->sum('refunded_cents'),
                $fees,
                $estimated,
            );
        }

        return $lines;
    }
}
