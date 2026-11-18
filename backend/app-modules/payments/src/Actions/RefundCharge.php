<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use PHPeste\Payments\Data\RefundResult;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Models\PaymentAccount;

class RefundCharge
{
    public function __construct(private readonly GatewayRegistry $gateways) {}

    public function handle(string $editionId, string $accountId, string $chargeId, int $amountCents): RefundResult
    {
        $account = PaymentAccount::query()->forEdition($editionId)->findOrFail($accountId);

        return $this->gateways->driver($account->driver)->refund($account->gatewayCredentials(), $chargeId, $amountCents);
    }
}
