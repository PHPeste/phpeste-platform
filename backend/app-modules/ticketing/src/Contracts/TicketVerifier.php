<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Contracts;

use PHPeste\Ticketing\Data\TicketCheck;

interface TicketVerifier
{
    public function check(string $editionId, string $qrToken): TicketCheck;
}
