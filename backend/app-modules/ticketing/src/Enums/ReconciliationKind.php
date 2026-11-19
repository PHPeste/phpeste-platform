<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum ReconciliationKind: string
{
    case PaidWithoutCharge = 'paid_without_charge';
    case ChargeWithoutOrder = 'charge_without_order';
    case AmountMismatch = 'amount_mismatch';
    case DuplicatePayment = 'duplicate_payment';
    case Disputed = 'disputed';
    case RefundUnconfirmed = 'refund_unconfirmed';

    public function label(): string
    {
        return match ($this) {
            self::PaidWithoutCharge => 'Pedido pago sem pagamento confirmado no gateway',
            self::ChargeWithoutOrder => 'Pagamento recebido para pedido que não está pago',
            self::AmountMismatch => 'Valor pago diferente do total do pedido',
            self::DuplicatePayment => 'Pedido pago mais de uma vez',
            self::Disputed => 'Pagamento em contestação no cartão',
            self::RefundUnconfirmed => 'Reembolso sem confirmação do gateway',
        };
    }

    public function advice(): string
    {
        return match ($this) {
            self::PaidWithoutCharge => 'Confira no painel do gateway se o pagamento existe. Se não existir, cancele o pedido.',
            self::ChargeWithoutOrder => 'O pagamento chegou depois de a reserva expirar ou de o pedido ser cancelado. Reembolse com o motivo "Pago depois de a reserva expirar".',
            self::AmountMismatch => 'Confira o valor no painel do gateway e reembolse a diferença, se for o caso.',
            self::DuplicatePayment => 'Reembolse o pagamento repetido.',
            self::Disputed => 'Responda à contestação no painel do gateway, com o comprovante de compra e de presença.',
            self::RefundUnconfirmed => 'Confira no painel do gateway se o estorno saiu e registre o resultado no reembolso.',
        };
    }
}
