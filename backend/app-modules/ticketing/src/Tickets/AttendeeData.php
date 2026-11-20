<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Tickets;

use Illuminate\Database\Eloquent\Builder;
use PHPeste\Ticketing\Enums\TicketStatus;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketTransfer;

/**
 * O que conta como "dados faltando" num ingresso: o nome do crachá e o
 * tamanho da camiseta. Empresa e acessibilidade são opcionais.
 */
class AttendeeData
{
    public const array REQUIRED = ['badge_name', 'tshirt_size'];

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function missing(Builder $query): Builder
    {
        return $query->where('status', TicketStatus::Active->value)
            ->where(fn (Builder $inner) => $inner->whereNull('badge_name')->orWhereNull('tshirt_size'));
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function transferred(Builder $query): Builder
    {
        return $query->whereHas('transfers');
    }

    /**
     * Quem tinha o ingresso antes da primeira transferência.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public function withPreviousHolder(Builder $query): Builder
    {
        return $query->addSelect(['transferred_from' => TicketTransfer::query()
            ->select('from_name')
            ->whereColumn('ticket_transfers.ticket_id', 'tickets.id')
            ->oldest()
            ->limit(1)]);
    }

    /**
     * @return list<string>
     */
    public function missingFields(Ticket $ticket): array
    {
        if (!$ticket->isActive()) {
            return [];
        }

        return array_values(array_filter(self::REQUIRED, fn (string $field): bool => $ticket->getAttribute($field) === null));
    }

    /**
     * Números da edição inteira para o topo da tela, sem seguir o filtro.
     *
     * @return array{participants: int, complete: int, missing: int, accessibility: int|null, transferred: int}
     */
    public function summary(bool $private): array
    {
        $active = fn (): Builder => Ticket::query()->where('status', TicketStatus::Active->value);
        $participants = $active()->count();
        $missing = $this->missing(Ticket::query())->count();

        return [
            'participants' => $participants,
            'complete' => $participants - $missing,
            'missing' => $missing,
            'accessibility' => $private ? $active()->whereNotNull('accessibility')->count() : null,
            'transferred' => $this->transferred($active())->count(),
        ];
    }
}
