<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum BatchStatus: string
{
    case Scheduled = 'scheduled';
    case OnSale = 'on_sale';
    case SoldOut = 'sold_out';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendado',
            self::OnSale => 'À venda',
            self::SoldOut => 'Esgotado',
            self::Ended => 'Encerrado',
        };
    }
}
