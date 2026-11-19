<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum ChargeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Failed => 'Recusado',
            self::Expired => 'Vencido',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
            self::Disputed => 'Em contestação',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Failed, self::Expired, self::Cancelled, self::Refunded], true);
    }
}
