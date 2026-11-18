<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use PHPeste\Payments\Contracts\AccountUsage;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;

class OrderLedger implements AccountUsage
{
    public function paidOrders(string $editionId, string $accountId): int
    {
        return Order::query()->forEdition($editionId)
            ->where('payment_account_id', $accountId)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Refunded->value])
            ->count();
    }
}
