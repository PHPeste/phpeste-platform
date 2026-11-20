<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Events\AttendeeDataReminderSent;
use PHPeste\Ticketing\Jobs\SendAttendeeDataReminders;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\AttendeeData;

/**
 * Cobra por e-mail quem ainda não preencheu o crachá ou a camiseta. Uma
 * mensagem por pessoa, mesmo com vários ingressos. Como na importação, a
 * organização vê quantas pessoas vão receber e confirma esse número.
 */
class RemindMissingAttendeeData
{
    public function __construct(private readonly AttendeeData $data) {}

    public function recipients(): int
    {
        return $this->data->missing(Ticket::query())->distinct()->count('holder_email');
    }

    public function handle(string $editionId, bool $confirm, ?int $recipients, ?string $actorId): int
    {
        $pending = $this->recipients();

        if ($pending === 0) {
            throw ValidationException::withMessages(['recipients' => 'Ninguém está com dados faltando.']);
        }

        if (!$confirm || $recipients !== $pending) {
            throw ValidationException::withMessages(['confirm' => sprintf('Confirme a cobrança por e-mail para %d %s.', $pending, $pending === 1 ? 'pessoa' : 'pessoas')]);
        }

        DB::transaction(function () use ($editionId, $pending, $actorId): void {
            event(new AttendeeDataReminderSent($editionId, $pending, $actorId));
            dispatch(new SendAttendeeDataReminders($editionId));
        });

        return $pending;
    }
}
