<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Data\ChargeRecord;
use PHPeste\Payments\Data\RefundRecord;
use PHPeste\Payments\Data\RefundRequest;

interface PaymentLedger
{
    /**
     * @return list<ChargeRecord>
     */
    public function charges(string $editionId): array;

    public function refresh(string $editionId, CarbonInterface $since): int;

    /**
     * @throws ValidationException
     */
    public function refund(RefundRequest $request): RefundRecord;

    /**
     * @return list<RefundRecord>
     */
    public function refunds(string $editionId, ?string $reference = null): array;

    /**
     * @throws ValidationException
     */
    public function settleRefund(string $editionId, string $refundId, bool $completed, ?string $actorId): RefundRecord;
}
