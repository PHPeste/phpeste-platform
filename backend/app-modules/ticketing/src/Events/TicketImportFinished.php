<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class TicketImportFinished implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $importId,
        public readonly int $imported,
        public readonly int $duplicates,
        public readonly int $rejected,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.import_finished', 'ticket_import', $this->importId, null, null, ['imported' => $this->imported, 'duplicates' => $this->duplicates, 'rejected' => $this->rejected]);
    }
}
