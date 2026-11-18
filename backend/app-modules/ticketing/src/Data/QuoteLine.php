<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class QuoteLine
{
    public function __construct(
        public string $ticketTypeId,
        public int $quantity,
        public int $unitPriceCents,
        public int $discountCents,
    ) {}

    public function subtotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() - $this->discountCents;
    }
}
