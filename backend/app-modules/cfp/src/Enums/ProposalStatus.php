<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Submitted => 'Enviada',
            self::Withdrawn => 'Retirada',
        };
    }
}
