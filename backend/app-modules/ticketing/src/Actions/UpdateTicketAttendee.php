<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Events\TicketAttendeeUpdated;
use PHPeste\Ticketing\Models\Ticket;

/**
 * Muda os dados de quem usa o ingresso e registra na auditoria o que mudou,
 * com o valor de antes e o de depois. A acessibilidade entra só como
 * "mudou", sem o texto.
 */
class UpdateTicketAttendee
{
    public const array FIELDS = ['holder_name', 'badge_name', 'company', 'tshirt_size', 'accessibility'];

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Ticket $ticket, array $data, ?string $actorId = null): Ticket
    {
        if (!$ticket->isActive()) {
            throw ValidationException::withMessages(['ticket' => 'Ingresso cancelado não pode ser alterado.']);
        }

        $ticket->fill(array_intersect_key($data, array_flip(self::FIELDS)));
        $changed = array_keys($ticket->getDirty());

        if ($changed === []) {
            return $ticket;
        }

        $before = [];
        $after = [];

        foreach ($changed as $field) {
            $before[$field] = $this->value($field, $ticket->getOriginal($field));
            $after[$field] = $this->value($field, $ticket->getAttribute($field));
        }

        DB::transaction(function () use ($ticket, $before, $after, $actorId): void {
            $ticket->save();
            event(new TicketAttendeeUpdated($ticket->edition_id, $ticket->id, $before, $after, $actorId));
        });

        return $ticket->refresh();
    }

    private function value(string $field, mixed $value): ?string
    {
        if ($field === 'accessibility') {
            return $value === null ? null : 'preenchido';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
