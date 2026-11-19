<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class OrderRefunded implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $orderId,
        public readonly string $refundId,
        public readonly int $amountCents,
        public readonly string $reason,
        public readonly ?string $note,
        public readonly int $cancelledTickets,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.order_refunded', 'order', $this->orderId, $this->actorId, null, [
            'refund_id' => $this->refundId,
            'amount_cents' => $this->amountCents,
            'reason' => $this->reason,
            'note' => $this->note,
            'cancelled_tickets' => $this->cancelledTickets,
        ]);
    }
}
