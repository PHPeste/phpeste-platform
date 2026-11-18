<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum PaymentMethod: string
{
    case Pix = 'pix';
    case Card = 'card';
    case Boleto = 'boleto';

    public function label(): string
    {
        return match ($this) {
            self::Pix => 'Pix',
            self::Card => 'Cartão de crédito',
            self::Boleto => 'Boleto',
        };
    }
}
