<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum RefundReason: string
{
    case BuyerRequest = 'buyer_request';
    case EventChanged = 'event_changed';
    case Duplicate = 'duplicate';
    case PaidAfterExpiry = 'paid_after_expiry';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BuyerRequest => 'Pedido de quem comprou',
            self::EventChanged => 'Evento cancelado ou alterado',
            self::Duplicate => 'Pagamento em duplicidade',
            self::PaidAfterExpiry => 'Pago depois de a reserva expirar',
            self::Other => 'Outro motivo',
        };
    }
}
