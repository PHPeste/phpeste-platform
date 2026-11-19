<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use PHPeste\Payments\Enums\RefundReason;

final readonly class RefundRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $editionId,
        public string $reference,
        public int $amountCents,
        public RefundReason $reason,
        public ?string $note,
        public ?string $actorId,
        public array $metadata = [],
    ) {}
}
