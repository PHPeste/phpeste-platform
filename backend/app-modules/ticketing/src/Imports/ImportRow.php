<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

/**
 * Uma linha do CSV já validada, pronta para virar participante.
 */
final readonly class ImportRow
{
    public function __construct(
        public string $name,
        public string $email,
        public string $ticketTypeId,
        public ?string $orderReference,
        public ?string $ticketCode,
        public string $buyerName,
        public string $buyerEmail,
        public ?string $badgeName,
        public ?string $company,
    ) {}
}
