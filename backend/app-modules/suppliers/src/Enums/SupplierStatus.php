<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum SupplierStatus: string
{
    case ToContact = 'to_contact';
    case Quoting = 'quoting';
    case Hired = 'hired';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::ToContact => 'A contatar',
            self::Quoting => 'Em orçamento',
            self::Hired => 'Contratado',
            self::Declined => 'Recusado',
        };
    }
}
