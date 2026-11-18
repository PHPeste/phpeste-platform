<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;
use PHPeste\Payments\Enums\ChargeStatus;

final readonly class ChargeSnapshot
{
    public function __construct(
        public string $chargeId,
        public ChargeStatus $status,
        public int $amountCents,
        public ?int $feeCents = null,
        public ?Carbon $paidAt = null,
        public ?string $failureReason = null,
    ) {}
}
