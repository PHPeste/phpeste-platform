<?php

declare(strict_types=1);

namespace PHPeste\Payments\Drivers\Asaas;

use PHPeste\Payments\Enums\ChargeStatus;

final class AsaasStatus
{
    public static function map(string $status, bool $deleted = false): ChargeStatus
    {
        if ($deleted) {
            return ChargeStatus::Cancelled;
        }

        return match ($status) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED', 'REFUND_REQUESTED', 'REFUND_IN_PROGRESS' => ChargeStatus::Paid,
            'OVERDUE', 'DUNNING_REQUESTED' => ChargeStatus::Expired,
            'REFUNDED' => ChargeStatus::Refunded,
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => ChargeStatus::Disputed,
            default => ChargeStatus::Pending,
        };
    }

    public static function fromEvent(string $event): ?ChargeStatus
    {
        return match ($event) {
            'PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED' => ChargeStatus::Paid,
            'PAYMENT_OVERDUE' => ChargeStatus::Expired,
            'PAYMENT_DELETED' => ChargeStatus::Cancelled,
            'PAYMENT_REFUNDED' => ChargeStatus::Refunded,
            'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', 'PAYMENT_REPROVED_BY_RISK_ANALYSIS' => ChargeStatus::Failed,
            'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE', 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL' => ChargeStatus::Disputed,
            default => null,
        };
    }
}
