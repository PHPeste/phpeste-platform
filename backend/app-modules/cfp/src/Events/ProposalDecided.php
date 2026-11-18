<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Cfp\Enums\ProposalDecision;

class ProposalDecided implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $proposalId,
        public readonly ProposalDecision $decision,
        public readonly ?ProposalDecision $previous,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'cfp.proposal_decided', 'proposal', $this->proposalId, $this->actorId, ['decision' => $this->previous?->value], ['decision' => $this->decision->value]);
    }
}
