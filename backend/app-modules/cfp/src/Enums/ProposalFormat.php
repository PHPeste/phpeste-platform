<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalFormat: string
{
    case Talk = 'talk';
    case Workshop = 'workshop';
    case Panel = 'panel';

    public function label(): string
    {
        return match ($this) {
            self::Talk => 'Palestra de 30 min',
            self::Workshop => 'Oficina de 2 h',
            self::Panel => 'Painel de 45 min',
        };
    }

    public function minutes(): int
    {
        return match ($this) {
            self::Talk => 30,
            self::Workshop => 120,
            self::Panel => 45,
        };
    }
}
