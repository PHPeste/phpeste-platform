<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum GatewayEnvironment: string
{
    case Sandbox = 'sandbox';
    case Production = 'production';

    public function label(): string
    {
        return match ($this) {
            self::Sandbox => 'Sandbox (testes)',
            self::Production => 'Produção',
        };
    }
}
