<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum SellOutBehavior: string
{
    case NextBatch = 'next';
    case Close = 'close';

    public function label(): string
    {
        return match ($this) {
            self::NextBatch => 'Abrir o próximo lote',
            self::Close => 'Encerrar vendas',
        };
    }
}
