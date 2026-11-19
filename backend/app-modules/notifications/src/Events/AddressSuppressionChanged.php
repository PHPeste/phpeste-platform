<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

/**
 * Endereço bloqueado (bounce permanente ou spam, pelo webhook) ou liberado
 * pela organização.
 */
class AddressSuppressionChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $suppressionId,
        public readonly string $email,
        public readonly string $reason,
        public readonly bool $lifted,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord(
            $this->editionId,
            $this->lifted ? 'notifications.suppression_lifted' : 'notifications.address_suppressed',
            'email_suppression',
            $this->suppressionId,
            $this->actorId,
            null,
            ['email' => $this->email, 'reason' => $this->reason],
        );
    }
}
