<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalLevel: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Iniciante',
            self::Intermediate => 'Intermediário',
            self::Advanced => 'Avançado',
        };
    }
}
