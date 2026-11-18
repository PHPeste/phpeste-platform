<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class Allocation
{
    public function __construct(
        public string $editionId,
        public string $ticketTypeId,
        public string $ticketBatchId,
        public string $batchName,
        public int $quantity,
        public int $unitPriceCents,
    ) {}
}
