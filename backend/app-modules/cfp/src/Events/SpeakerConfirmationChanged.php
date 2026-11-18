<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Cfp\Enums\ConfirmationStatus;

class SpeakerConfirmationChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $proposalId,
        public readonly ConfirmationStatus $from,
        public readonly ConfirmationStatus $to,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'cfp.confirmation_changed', 'proposal', $this->proposalId, $this->actorId, ['status' => $this->from->value], ['status' => $this->to->value]);
    }
}
