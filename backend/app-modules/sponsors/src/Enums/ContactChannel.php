<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Enums;

enum ContactChannel: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Meeting = 'meeting';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::Whatsapp => 'WhatsApp',
            self::Meeting => 'Reunião',
            self::Other => 'Outro',
        };
    }
}
