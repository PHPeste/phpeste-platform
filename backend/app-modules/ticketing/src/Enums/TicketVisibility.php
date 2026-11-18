<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum TicketVisibility: string
{
    case Public = 'public';
    case Code = 'code';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Qualquer pessoa',
            self::Code => 'Quem tem o código',
            self::Hidden => 'Só por convite da organização',
        };
    }
}
