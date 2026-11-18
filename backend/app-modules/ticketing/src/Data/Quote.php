<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class Quote
{
    /**
     * @param  list<QuoteLine>  $lines
     */
    public function __construct(
        public array $lines,
        public ?string $couponId,
        public ?string $couponCode,
    ) {}

    public function subtotalCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $line): int => $line->subtotalCents(), $this->lines));
    }

    public function discountCents(): int
    {
        return array_sum(array_map(fn (QuoteLine $line): int => $line->discountCents, $this->lines));
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() - $this->discountCents();
    }

    public function requiresPayment(): bool
    {
        return $this->totalCents() > 0;
    }
}
