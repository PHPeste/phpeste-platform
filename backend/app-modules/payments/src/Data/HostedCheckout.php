<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

final readonly class HostedCheckout
{
    public function __construct(
        public string $chargeId,
        public string $url,
    ) {}
}
