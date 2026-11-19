<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum RefundStatus: string
{
    case Unconfirmed = 'unconfirmed';
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unconfirmed => 'A conferir no gateway',
            self::Pending => 'Em processamento no gateway',
            self::Completed => 'Reembolsado',
            self::Failed => 'Não realizado',
        };
    }

    public function isAccepted(): bool
    {
        return in_array($this, [self::Pending, self::Completed], true);
    }
}
