<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum TicketStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Válido',
            self::Cancelled => 'Cancelado',
        };
    }
}
