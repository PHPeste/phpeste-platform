<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalOrigin: string
{
    case Cfp = 'cfp';
    case Invited = 'invited';

    public function label(): string
    {
        return match ($this) {
            self::Cfp => 'Pelo CFP',
            self::Invited => 'Convite da organização',
        };
    }
}
