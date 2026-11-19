<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

class TicketTransferred implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $ticketId,
        public readonly string $transferId,
        public readonly string $channel,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.ticket_transferred', 'ticket', $this->ticketId, $this->actorId, null, ['transfer_id' => $this->transferId, 'channel' => $this->channel]);
    }
}
