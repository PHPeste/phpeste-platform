<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;

final readonly class ChargeRequest
{
    public function __construct(
        public string $reference,
        public int $amountCents,
        public string $description,
        public Payer $payer,
        public Carbon $expiresAt,
    ) {}
}
