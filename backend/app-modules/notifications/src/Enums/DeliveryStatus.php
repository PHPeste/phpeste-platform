<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Enums;

enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Failed = 'failed';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Na fila',
            self::Sent => 'Enviado',
            self::Delivered => 'Entregue',
            self::Bounced => 'Voltou (bounce)',
            self::Complained => 'Marcado como spam',
            self::Failed => 'Falhou',
            self::Suppressed => 'Não enviado: endereço bloqueado',
        };
    }
}
