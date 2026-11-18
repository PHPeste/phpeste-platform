<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;

final readonly class PixCharge
{
    public function __construct(
        public string $chargeId,
        public string $copyPaste,
        public string $qrCodeBase64,
        public Carbon $expiresAt,
    ) {}
}
