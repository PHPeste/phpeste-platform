<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Sponsors\Enums\SponsorshipStage;

class SponsorshipStageChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $sponsorshipId,
        public readonly ?SponsorshipStage $from,
        public readonly SponsorshipStage $to,
        public readonly bool $overSlots,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'sponsors.stage_changed', 'sponsorship', $this->sponsorshipId, $this->actorId, ['stage' => $this->from?->value], ['stage' => $this->to->value, 'over_slots' => $this->overSlots]);
    }
}
