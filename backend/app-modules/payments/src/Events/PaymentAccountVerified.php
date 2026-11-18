<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class PaymentAccountVerified implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $accountId,
        public readonly string $check,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, "payments.{$this->check}_verified", 'payment_account', $this->accountId, $this->actorId, null, ['check' => $this->check]);
    }
}
