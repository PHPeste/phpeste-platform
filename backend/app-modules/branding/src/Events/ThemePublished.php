<?php

declare(strict_types=1);

namespace PHPeste\Branding\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class ThemePublished implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $editionId) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'branding.theme_published', 'edition', $this->editionId);
    }
}
