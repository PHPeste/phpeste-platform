<?php

declare(strict_types=1);

namespace PHPeste\Community\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Community\Enums\EditionStatus;

class EditionStatusChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly EditionStatus $from,
        public readonly EditionStatus $to,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'edition.status_changed', 'edition', $this->editionId, null, ['status' => $this->from->value], ['status' => $this->to->value]);
    }
}
