<?php

declare(strict_types=1);

namespace PHPeste\Audit\Listeners;

use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Audit\Support\AuditRedactor;

class RecordAuditableEvent
{
    public function handle(Auditable $event): void
    {
        $record = $event->auditRecord();
        $request = request();
        $requestId = $request->attributes->get('request_id');
        $fromRequest = is_string($requestId) && $requestId !== '';
        $authenticated = auth()->id();

        AuditLog::create([
            'edition_id' => $record->editionId,
            'actor_id' => $record->actorId ?? (is_string($authenticated) ? $authenticated : null),
            'action' => $record->action,
            'subject_type' => $record->subjectType,
            'subject_id' => $record->subjectId,
            'before' => AuditRedactor::redact($record->before),
            'after' => AuditRedactor::redact($record->after),
            'ip' => $fromRequest ? $request->ip() : null,
            'request_id' => $fromRequest ? $requestId : null,
            'created_at' => now(),
        ]);
    }
}
