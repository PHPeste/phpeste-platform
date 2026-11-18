<?php

declare(strict_types=1);

namespace PHPeste\Community\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class TeamChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public const string INVITED = 'team.invited';

    public const string INVITATION_REVOKED = 'team.invitation_revoked';

    public const string JOINED = 'team.joined';

    public const string ROLE_CHANGED = 'team.role_changed';

    public const string REMOVED = 'team.removed';

    /**
     * @param  array<string, string|null>  $details
     */
    public function __construct(
        public readonly string $editionId,
        public readonly string $action,
        public readonly ?string $actorId,
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly array $details = [],
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, $this->action, $this->subjectType, $this->subjectId, $this->actorId, null, $this->details);
    }
}
