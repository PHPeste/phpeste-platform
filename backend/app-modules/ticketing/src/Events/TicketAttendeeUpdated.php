<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

/**
 * Os dados de quem usa o ingresso mudaram (nome, crachá, empresa, camiseta
 * ou acessibilidade). A acessibilidade é sensível: a auditoria guarda só que
 * ela mudou, nunca o texto.
 */
class TicketAttendeeUpdated implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, string|null>  $before
     * @param  array<string, string|null>  $after
     */
    public function __construct(
        public readonly string $editionId,
        public readonly string $ticketId,
        public readonly array $before,
        public readonly array $after,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'ticketing.ticket_attendee_updated', 'ticket', $this->ticketId, $this->actorId, $this->before, $this->after);
    }
}
