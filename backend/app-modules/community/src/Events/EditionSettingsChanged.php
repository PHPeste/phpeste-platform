<?php

declare(strict_types=1);

namespace PHPeste\Community\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class EditionSettingsChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const string ACTION = 'edition.settings_changed';

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public readonly string $editionId,
        public readonly ?string $actorId,
        public readonly array $before,
        public readonly array $after,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, self::ACTION, 'edition', $this->editionId, $this->actorId, $this->before, $this->after);
    }
}
