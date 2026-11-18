<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum CouponKind: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percentual',
            self::Fixed => 'Valor fixo por pedido',
        };
    }
}
