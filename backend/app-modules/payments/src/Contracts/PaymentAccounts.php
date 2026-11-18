<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

use PHPeste\Payments\Data\ReceivingAccount;

interface PaymentAccounts
{
    public function active(string $editionId): ?ReceivingAccount;

    public function ready(string $editionId): bool;
}
