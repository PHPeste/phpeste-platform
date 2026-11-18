<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class SpeakerInvited implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $proposalId,
        public readonly string $speakerId,
        public readonly string $email,
        public readonly string $title,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'cfp.speaker_invited', 'proposal', $this->proposalId, $this->actorId, null, ['email' => $this->email, 'proposta' => $this->title]);
    }
}
