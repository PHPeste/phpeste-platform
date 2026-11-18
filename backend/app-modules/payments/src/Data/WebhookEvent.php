<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use PHPeste\Payments\Enums\ChargeStatus;

final readonly class WebhookEvent
{
    public function __construct(
        public string $eventId,
        public string $type,
        public ?string $chargeId,
        public ?ChargeStatus $status,
    ) {}
}
