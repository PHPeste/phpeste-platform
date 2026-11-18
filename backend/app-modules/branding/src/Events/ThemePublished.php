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

    public const string PUBLISHED = 'branding.theme_published';

    public const string REVERTED = 'branding.theme_reverted';

    public function __construct(
        public readonly string $editionId,
        public readonly ?string $actorId = null,
        public readonly string $action = self::PUBLISHED,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, $this->action, 'edition', $this->editionId, $this->actorId);
    }
}
