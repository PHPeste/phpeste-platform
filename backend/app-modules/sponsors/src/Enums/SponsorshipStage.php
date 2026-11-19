<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Enums;

enum SponsorshipStage: string
{
    case Prospecting = 'prospecting';
    case Negotiating = 'negotiating';
    case Closed = 'closed';
    case Paid = 'paid';
    case Lost = 'lost';

    /**
     * @return list<string>
     */
    public static function committedValues(): array
    {
        return [self::Closed->value, self::Paid->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Prospecting => 'Prospecção',
            self::Negotiating => 'Negociando',
            self::Closed => 'Fechado',
            self::Paid => 'Pago',
            self::Lost => 'Perdido',
        };
    }

    /**
     * Acordo fechado ocupa vaga da cota e entra no total fechado.
     */
    public function committed(): bool
    {
        return in_array($this, [self::Closed, self::Paid], true);
    }

    public function open(): bool
    {
        return in_array($this, [self::Prospecting, self::Negotiating], true);
    }
}
