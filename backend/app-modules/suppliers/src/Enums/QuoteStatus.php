<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum QuoteStatus: string
{
    case Received = 'received';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Recebido',
            self::Accepted => 'Aceito',
            self::Rejected => 'Recusado',
        };
    }
}
