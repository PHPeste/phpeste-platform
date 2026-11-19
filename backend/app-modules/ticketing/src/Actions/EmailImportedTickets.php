<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Events\ImportedTicketsEmailed;
use PHPeste\Ticketing\Jobs\SendImportedTicketEmails;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Tickets\ImportedTickets;

/**
 * Manda o ingresso por e-mail para quem veio na importação. A importação
 * nunca manda e-mail sozinha: a organização vê quantas pessoas vão receber
 * e confirma esse número (`recipients`). Se o número mudou, recusa.
 */
class EmailImportedTickets
{
    public function __construct(private readonly ImportedTickets $tickets) {}

    public function handle(TicketImport $import, bool $confirm, ?int $recipients, ?string $actorId): int
    {
        if ($import->status !== ImportStatus::Completed) {
            throw ValidationException::withMessages(['import' => 'Espere a importação terminar para mandar os ingressos.']);
        }

        $pending = $this->tickets->unsent($import)->count();

        if ($pending === 0) {
            throw ValidationException::withMessages(['import' => 'Todos os ingressos desta importação já foram enviados.']);
        }

        if (!$confirm || $recipients !== $pending) {
            throw ValidationException::withMessages(['confirm' => sprintf('Confirme o envio do ingresso por e-mail para %d %s.', $pending, $pending === 1 ? 'pessoa' : 'pessoas')]);
        }

        DB::transaction(function () use ($import, $pending, $actorId): void {
            $import->update(['emails_requested_at' => now(), 'emails_requested_by' => $actorId]);
            event(new ImportedTicketsEmailed($import->edition_id, $import->id, $pending, $actorId));
            dispatch(new SendImportedTicketEmails($import->edition_id, $import->id));
        });

        return $pending;
    }
}
