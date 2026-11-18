<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ConfirmationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
    case NoResponse = 'no_response';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando resposta',
            self::Confirmed => 'Confirmada',
            self::Declined => 'Recusou',
            self::Withdrawn => 'Desistiu',
            self::NoResponse => 'Sem resposta',
            self::Cancelled => 'Cancelada',
        };
    }

    public function holdsSlot(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::NoResponse], true);
    }
}
