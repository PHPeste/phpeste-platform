<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\PaymentMethod;

final readonly class PaymentAttempt
{
    public function __construct(
        public string $id,
        public PaymentMethod $method,
        public ChargeStatus $status,
        public int $amountCents,
        public int $installments,
        public ?string $pixCopyPaste,
        public ?string $pixQrCode,
        public ?string $checkoutUrl,
        public ?Carbon $expiresAt,
        public ?Carbon $paidAt,
        public ?string $failureReason,
    ) {}
}
