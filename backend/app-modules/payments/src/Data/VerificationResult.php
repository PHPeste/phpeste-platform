<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

final readonly class VerificationResult
{
    public function __construct(
        public bool $valid,
        public ?string $message = null,
        public ?string $accountName = null,
    ) {}
}
