<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Enums;

enum DeliverableStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'A fazer',
            self::InProgress => 'Em andamento',
            self::Done => 'Entregue',
        };
    }
}
