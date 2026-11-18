<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum ProposalLanguage: string
{
    case Portuguese = 'pt_BR';
    case English = 'en';
    case Spanish = 'es';

    public function label(): string
    {
        return match ($this) {
            self::Portuguese => 'Português',
            self::English => 'Inglês',
            self::Spanish => 'Espanhol',
        };
    }
}
