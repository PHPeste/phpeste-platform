<?php

declare(strict_types=1);

namespace PHPeste\Audit\Listeners;

use PHPeste\Audit\Models\AuditEntry;
use PHPeste\Community\Events\TeamChanged;

class RecordTeamChange
{
    public function handle(TeamChanged $event): void
    {
        AuditEntry::create([
            'edition_id' => $event->editionId,
            'actor_id' => $event->actorId,
            'action' => $event->action,
            'subject_type' => $event->subjectType,
            'subject_id' => $event->subjectId,
            'details' => $event->details,
            'created_at' => now(),
        ]);
    }
}
