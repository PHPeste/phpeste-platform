<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;
use PHPeste\Payments\Enums\RefundReason;
use PHPeste\Payments\Enums\RefundStatus;

final readonly class RefundRecord
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public string $reference,
        public string $chargeId,
        public int $amountCents,
        public RefundStatus $status,
        public RefundReason $reason,
        public ?string $note,
        public array $metadata,
        public ?string $failureReason,
        public ?string $requestedBy,
        public Carbon $createdAt,
        public ?Carbon $completedAt,
    ) {}
}
