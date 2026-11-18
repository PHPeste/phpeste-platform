<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Ticketing\Enums\OrderStatus;

class OrderStatusChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $orderId,
        public readonly OrderStatus $from,
        public readonly OrderStatus $to,
        public readonly ?string $actorId = null,
        public readonly ?string $reason = null,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.order_status_changed', 'order', $this->orderId, $this->actorId, ['status' => $this->from->value], ['status' => $this->to->value, 'reason' => $this->reason]);
    }
}
