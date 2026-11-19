<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketImport;

/**
 * Decide o que acontece com uma linha: entra, é repetida ou é rejeitada.
 *
 * Repetida é a linha cujo código do ingresso já foi importado nesta edição
 * ou, sem código, cujo e-mail já tem ingresso válido do mesmo tipo. Assim
 * reimportar o mesmo arquivo (ou retomar uma importação no meio) não cria
 * ninguém duas vezes. Usado pela prévia (sem gravar nada) e pela importação.
 */
final readonly class RowEvaluator
{
    public function __construct(private RowParser $parser) {}

    /**
     * @param  list<string>  $values
     */
    public function evaluate(TicketImport $import, array $values): RowEvaluation
    {
        $row = $this->parser->parse($import, $values);

        if (is_string($row)) {
            return RowEvaluation::rejected($row);
        }

        if ($row->ticketCode !== null) {
            $taken = OrderItem::query()->forEdition($import->edition_id)->where('external_code', $row->ticketCode)->exists();

            return $taken ? RowEvaluation::duplicate(sprintf('O ingresso de código "%s" já foi importado.', $row->ticketCode)) : RowEvaluation::import($row);
        }

        $holds = Ticket::query()->forEdition($import->edition_id)
            ->where('holder_email', $row->email)
            ->where('ticket_type_id', $row->ticketTypeId)
            ->where('status', TicketStatus::Active->value)
            ->exists();

        return $holds ? RowEvaluation::duplicate(sprintf('%s já tem um ingresso deste tipo.', $row->email)) : RowEvaluation::import($row);
    }
}
