<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Payments\Data\StartPayment;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Gateway\GatewayRequestFailed;

interface PaymentProcessor
{
    /**
     * @throws ValidationException
     */
    public function start(StartPayment $payment): PaymentAttempt;

    public function current(string $editionId, string $reference, bool $refresh = false): ?PaymentAttempt;

    public function cancelPending(string $editionId, string $reference): void;

    /**
     * @throws GatewayRequestFailed
     */
    public function sync(string $editionId, string $accountId, string $gatewayChargeId): ?ChargeStatus;
}
