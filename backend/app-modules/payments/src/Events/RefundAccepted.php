<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Payments\Enums\RefundReason;

class RefundAccepted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $editionId,
        public readonly string $refundId,
        public readonly string $reference,
        public readonly int $amountCents,
        public readonly RefundReason $reason,
        public readonly ?string $note,
        public readonly array $metadata,
        public readonly ?string $actorId,
    ) {}
}
