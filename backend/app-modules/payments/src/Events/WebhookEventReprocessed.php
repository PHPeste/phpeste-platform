<?php

declare(strict_types=1);

namespace PHPeste\Payments\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class WebhookEventReprocessed implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $webhookEventId,
        public readonly string $previousStatus,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'payments.webhook_reprocessed', 'webhook_event', $this->webhookEventId, $this->actorId, null, ['previous_status' => $this->previousStatus]);
    }
}
