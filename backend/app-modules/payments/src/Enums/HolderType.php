<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum HolderType: string
{
    case Individual = 'individual';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Individual => 'Pessoa física (CPF)',
            self::Company => 'Pessoa jurídica (CNPJ)',
        };
    }
}
