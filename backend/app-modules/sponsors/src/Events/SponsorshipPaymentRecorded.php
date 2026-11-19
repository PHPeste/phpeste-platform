<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class SponsorshipPaymentRecorded implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $sponsorshipId,
        public readonly string $paymentId,
        public readonly int $amountCents,
        public readonly bool $removed,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord(
            $this->editionId,
            $this->removed ? 'sponsors.payment_removed' : 'sponsors.payment_recorded',
            'sponsorship',
            $this->sponsorshipId,
            $this->actorId,
            null,
            ['payment_id' => $this->paymentId, 'amount_cents' => $this->amountCents],
        );
    }
}
