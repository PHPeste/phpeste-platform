<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Tickets\AttendeeData;
use PHPeste\Ticketing\Tickets\TicketMailer;

/**
 * Manda a cobrança de dados para cada pessoa com ingresso incompleto, uma
 * vez por e-mail, olhando os dados no momento do envio.
 */
class SendAttendeeDataReminders implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly string $editionId)
    {
        $this->afterCommit();
    }

    public function handle(CurrentEdition $currentEdition, AttendeeData $data, TicketMailer $mailer): void
    {
        $currentEdition->run($this->editionId, function () use ($data, $mailer): void {
            $sent = [];

            $data->missing(Ticket::query())->orderBy('id')->chunkById(200, function ($chunk) use ($mailer, &$sent): void {
                foreach ($chunk as $ticket) {
                    $email = mb_strtolower($ticket->holder_email);

                    if (isset($sent[$email])) {
                        continue;
                    }

                    $sent[$email] = true;
                    $mailer->dataReminder($ticket);
                }
            });
        });
    }
}
