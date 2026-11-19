<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Payments\Enums\ChargeStatus;

class ChargeStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $chargeId,
        public readonly string $reference,
        public readonly ChargeStatus $from,
        public readonly ChargeStatus $to,
        public readonly int $amountCents,
    ) {}
}
