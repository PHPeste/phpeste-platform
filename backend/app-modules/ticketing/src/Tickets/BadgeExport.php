<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Models\Ticket;

/**
 * CSV para a gráfica dos crachás: só ingressos válidos, com o nome do
 * crachá (ou o nome da pessoa, se ela não escolheu). Sem e-mail e sem
 * acessibilidade.
 */
class BadgeExport
{
    /**
     * @param  resource  $out
     */
    public function write($out): void
    {
        fwrite($out, "\u{FEFF}");
        fputcsv($out, ['Código', 'Nome no crachá', 'Nome completo', 'Empresa', 'Ingresso', 'Camiseta'], ';', '"', '');

        Ticket::query()->with('item')
            ->where('status', TicketStatus::Active->value)
            ->orderBy('id')
            ->chunkById(500, function ($tickets) use ($out): void {
                foreach ($tickets as $ticket) {
                    fputcsv($out, [
                        $ticket->code,
                        $this->safe($ticket->badge_name ?? $ticket->holder_name),
                        $this->safe($ticket->holder_name),
                        $this->safe($ticket->company ?? ''),
                        $this->safe($ticket->item->ticket_type_name),
                        $ticket->tshirt_size->value ?? '',
                    ], ';', '"', '');
                }
            });
    }

    /**
     * Evita que a planilha execute fórmula vinda do nome digitado.
     */
    private function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
