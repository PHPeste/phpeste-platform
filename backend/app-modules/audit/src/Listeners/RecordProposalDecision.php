<?php

declare(strict_types=1);

namespace PHPeste\Audit\Listeners;

use PHPeste\Audit\Models\AuditEntry;
use PHPeste\Cfp\Events\ProposalDecided;

class RecordProposalDecision
{
    public function handle(ProposalDecided $event): void
    {
        AuditEntry::create([
            'edition_id' => $event->editionId,
            'actor_id' => $event->actorId,
            'action' => 'cfp.proposal_decided',
            'subject_type' => 'proposal',
            'subject_id' => $event->proposalId,
            'details' => ['from' => $event->previous?->value, 'to' => $event->decision->value],
            'created_at' => now(),
        ]);
    }
}
