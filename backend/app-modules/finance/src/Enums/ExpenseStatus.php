<?php

declare(strict_types=1);

namespace PHPeste\Finance\Enums;

enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'A pagar',
            self::Paid => 'Paga',
            self::Cancelled => 'Cancelada',
        };
    }
}
