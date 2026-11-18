<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class PaymentAccountChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $current
     */
    public function __construct(
        public readonly string $editionId,
        public readonly string $accountId,
        public readonly ?array $previous,
        public readonly array $current,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'payments.account_changed', 'payment_account', $this->accountId, $this->actorId, $this->previous, $this->current);
    }
}
