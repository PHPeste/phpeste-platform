<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

final readonly class RefundResult
{
    public function __construct(
        public string $refundId,
        public int $amountCents,
        public bool $completed,
    ) {}
}
