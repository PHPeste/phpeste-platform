<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

interface AccountUsage
{
    public function paidOrders(string $editionId, string $accountId): int;
}
