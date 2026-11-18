<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Enums;

enum CfpStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Em preparação',
            self::Scheduled => 'Agendado',
            self::Open => 'CFP aberto',
            self::Closed => 'CFP encerrado',
        };
    }
}
