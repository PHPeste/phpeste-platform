<?php

declare(strict_types=1);

namespace PHPeste\Audit\Support;

use PHPeste\Audit\Contracts\AuditTrail;
use PHPeste\Audit\Data\AuditEntry;
use PHPeste\Audit\Models\AuditLog;

class DatabaseAuditTrail implements AuditTrail
{
    public function forSubject(string $editionId, string $subjectType, string $subjectId, int $limit = 200): array
    {
        return array_values(AuditLog::query()
            ->where('edition_id', $editionId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->oldest('created_at')
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log): AuditEntry => new AuditEntry($log->id, $log->action, $log->actor_id, $log->before, $log->after, $log->created_at->toImmutable()))
            ->all());
    }
}
