<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use PHPeste\Audit\Contracts\AuditTrail;
use PHPeste\Audit\Data\AuditEntry;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Ticketing\Models\Ticket;

/**
 * As edições dos dados do participante, em frases prontas para o painel:
 * quem mudou, quando e de quê para quê.
 */
class AttendeeHistory
{
    private const array LABELS = [
        'holder_name' => 'Nome',
        'badge_name' => 'Nome no crachá',
        'company' => 'Empresa',
        'tshirt_size' => 'Camiseta',
        'accessibility' => 'Acessibilidade',
    ];

    public function __construct(
        private readonly AuditTrail $trail,
        private readonly UserAccounts $accounts,
    ) {}

    /**
     * @return list<array{id: string, changes: list<array{field: string, label: string, from: string|null, to: string|null}>, actor: array{id: string, name: string|null}|null, at: string}>
     */
    public function for(Ticket $ticket): array
    {
        $entries = array_values(array_filter(
            $this->trail->forSubject($ticket->edition_id, 'ticket', $ticket->id),
            fn (AuditEntry $entry): bool => $entry->action === 'ticketing.ticket_attendee_updated',
        ));

        $actorIds = array_values(array_unique(array_filter(array_map(fn (AuditEntry $entry): ?string => $entry->actorId, $entries))));
        $profiles = $actorIds === [] ? [] : $this->accounts->profiles($actorIds);

        return array_map(fn (AuditEntry $entry): array => [
            'id' => $entry->id,
            'changes' => $this->changes($entry),
            'actor' => $entry->actorId === null ? null : ['id' => $entry->actorId, 'name' => $profiles[$entry->actorId]['name'] ?? null],
            'at' => $entry->createdAt->utc()->toIso8601ZuluString(),
        ], $entries);
    }

    /**
     * @return list<array{field: string, label: string, from: string|null, to: string|null}>
     */
    private function changes(AuditEntry $entry): array
    {
        $changes = [];

        foreach (self::LABELS as $field => $label) {
            if (!array_key_exists($field, $entry->after ?? [])) {
                continue;
            }

            $from = $entry->before[$field] ?? null;
            $to = $entry->after[$field] ?? null;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from' => $field === 'accessibility' || !is_scalar($from) ? null : (string) $from,
                'to' => $field === 'accessibility' || !is_scalar($to) ? null : (string) $to,
            ];
        }

        return $changes;
    }
}
