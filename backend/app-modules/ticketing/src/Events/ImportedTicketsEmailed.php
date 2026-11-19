<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

/**
 * A organização confirmou o envio dos ingressos importados por e-mail.
 */
class ImportedTicketsEmailed implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $importId,
        public readonly int $recipients,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.import_emails_sent', 'ticket_import', $this->importId, $this->actorId, null, ['recipients' => $this->recipients]);
    }
}
