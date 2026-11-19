<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum ContactChannel: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Meeting = 'meeting';
    case Visit = 'visit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Phone => 'Telefone',
            self::Whatsapp => 'WhatsApp',
            self::Meeting => 'Reunião',
            self::Visit => 'Visita',
            self::Other => 'Outro',
        };
    }
}
