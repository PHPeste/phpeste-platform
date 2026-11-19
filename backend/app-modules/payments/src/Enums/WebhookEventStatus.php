<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum WebhookEventStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'Na fila',
            self::Processing => 'Processando',
            self::Processed => 'Processado',
            self::Ignored => 'Ignorado',
            self::Failed => 'Com falha',
        };
    }

    public function canReprocess(): bool
    {
        return in_array($this, [self::Failed, self::Ignored], true);
    }
}
