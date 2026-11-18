<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum OrderStatus: string
{
    case Created = 'created';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * @return list<string>
     */
    public static function holding(): array
    {
        return [self::Created->value, self::AwaitingPayment->value, self::Paid->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Criado',
            self::AwaitingPayment => 'Aguardando pagamento',
            self::Paid => 'Pago',
            self::Expired => 'Expirado',
            self::Cancelled => 'Cancelado',
            self::Refunded => 'Reembolsado',
        };
    }

    /**
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Created => [self::AwaitingPayment, self::Paid, self::Expired, self::Cancelled],
            self::AwaitingPayment => [self::Paid, self::Expired, self::Cancelled],
            self::Paid => [self::Refunded],
            self::Expired, self::Cancelled, self::Refunded => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->next(), true);
    }

    public function holdsInventory(): bool
    {
        return in_array($this, [self::Created, self::AwaitingPayment, self::Paid], true);
    }

    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Paid => 'paid_at',
            self::Expired => 'expired_at',
            self::Cancelled => 'cancelled_at',
            self::Refunded => 'refunded_at',
            self::Created, self::AwaitingPayment => null,
        };
    }
}
