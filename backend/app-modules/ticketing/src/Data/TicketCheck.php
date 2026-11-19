<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class TicketCheck
{
    public const string VALID = 'valid';

    public const string UNKNOWN = 'unknown';

    public const string REPLACED = 'replaced';

    public const string CANCELLED = 'cancelled';

    public function __construct(
        public string $result,
        public ?string $ticketId = null,
        public ?string $code = null,
        public ?string $holderName = null,
        public ?string $badgeName = null,
        public ?string $ticketTypeName = null,
    ) {}

    public function valid(): bool
    {
        return $this->result === self::VALID;
    }
}
