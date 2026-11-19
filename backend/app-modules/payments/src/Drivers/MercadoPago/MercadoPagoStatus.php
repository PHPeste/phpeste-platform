<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\MercadoPago;

use PHPeste\Payments\Enums\ChargeStatus;

final class MercadoPagoStatus
{
    public static function map(string $status, string $detail = ''): ChargeStatus
    {
        return match ($status) {
            'approved' => ChargeStatus::Paid,
            'rejected' => ChargeStatus::Failed,
            'cancelled' => $detail === 'expired' ? ChargeStatus::Expired : ChargeStatus::Cancelled,
            'refunded' => ChargeStatus::Refunded,
            'in_mediation', 'charged_back' => ChargeStatus::Disputed,
            default => ChargeStatus::Pending,
        };
    }

    public static function failure(string $detail): string
    {
        return match ($detail) {
            'cc_rejected_insufficient_amount' => 'Cartão sem limite para esta compra.',
            'cc_rejected_bad_filled_card_number', 'cc_rejected_bad_filled_date', 'cc_rejected_bad_filled_security_code', 'cc_rejected_bad_filled_other' => 'Algum dado do cartão está errado. Confira e tente de novo.',
            'cc_rejected_call_for_authorize' => 'O banco pediu para você autorizar a compra. Ligue para o banco ou use outro cartão.',
            'cc_rejected_card_disabled' => 'Cartão bloqueado. Ative com o banco ou use outro cartão.',
            'cc_rejected_duplicated_payment' => 'Você já fez um pagamento com este valor. Se precisar pagar de novo, use outro cartão ou Pix.',
            'cc_rejected_high_risk', 'rejected_high_risk' => 'O pagamento foi recusado pela análise de segurança. Tente pagar com Pix.',
            'cc_rejected_max_attempts' => 'Limite de tentativas atingido. Use outro cartão ou Pix.',
            default => 'Cartão recusado pelo emissor.',
        };
    }
}
