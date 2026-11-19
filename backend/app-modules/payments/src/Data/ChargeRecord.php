<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\PaymentMethod;

final readonly class ChargeRecord
{
    public function __construct(
        public string $id,
        public string $reference,
        public string $gatewayChargeId,
        public PaymentMethod $method,
        public ChargeStatus $status,
        public int $amountCents,
        public int $refundedCents,
        public ?int $feeCents,
        public ?Carbon $paidAt,
    ) {}
}
