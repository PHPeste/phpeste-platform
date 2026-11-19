<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class PaymentReminderSent implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $orderId,
        public readonly string $deliveryId,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.payment_reminder_sent', 'order', $this->orderId, $this->actorId, null, ['delivery_id' => $this->deliveryId]);
    }
}
