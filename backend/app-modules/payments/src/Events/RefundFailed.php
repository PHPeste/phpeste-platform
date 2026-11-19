<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class RefundFailed implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $refundId,
        public readonly string $reference,
        public readonly int $amountCents,
        public readonly string $failure,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'payments.refund_failed', 'payment_refund', $this->refundId, $this->actorId, null, ['reference' => $this->reference, 'amount_cents' => $this->amountCents, 'failure' => $this->failure]);
    }
}
