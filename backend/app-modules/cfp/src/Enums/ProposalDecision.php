<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalDecision: string
{
    case Accepted = 'accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Aprovada',
            self::Waitlisted => 'Lista de espera',
            self::Rejected => 'Recusada',
        };
    }
}
