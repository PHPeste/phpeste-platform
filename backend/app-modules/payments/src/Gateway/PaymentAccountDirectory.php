<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Data\ReceivingAccount;
use PHPeste\Payments\Models\PaymentAccount;

class PaymentAccountDirectory implements PaymentAccounts
{
    public function active(string $editionId): ?ReceivingAccount
    {
        return PaymentAccount::query()->forEdition($editionId)->whereNull('retired_at')->latest()->first()?->toReceivingAccount();
    }

    public function all(string $editionId): array
    {
        return array_values(PaymentAccount::query()->forEdition($editionId)->oldest()->orderBy('id')->get()
            ->map(fn (PaymentAccount $account): ReceivingAccount => $account->toReceivingAccount())
            ->all());
    }

    public function ready(string $editionId): bool
    {
        return $this->active($editionId)->ready ?? false;
    }
}
