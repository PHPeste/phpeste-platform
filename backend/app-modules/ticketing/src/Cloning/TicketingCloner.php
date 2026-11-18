<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

class TicketingCloner implements EditionCloner
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function key(): string
    {
        return 'ticketing';
    }

    public function label(): string
    {
        return 'Ingressos: tipos, lotes, perguntas e capacidade (sem datas e sem vendas)';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $types = $this->currentEdition->run($sourceEditionId, fn () => TicketType::query()->with('batches')->get());
        $settings = TicketingSetting::query()->forEdition($sourceEditionId)->first();

        if ($settings !== null) {
            TicketingSetting::create([
                'edition_id' => $targetEditionId,
                'capacity' => $settings->capacity,
                'reservation_minutes' => $settings->reservation_minutes,
                'max_tickets_per_buyer' => $settings->max_tickets_per_buyer,
                'gateway_fee_basis_points' => $settings->gateway_fee_basis_points,
            ]);
        }

        $types->each(function (TicketType $type) use ($targetEditionId): void {
            $copy = TicketType::create([
                'edition_id' => $targetEditionId,
                'name' => $type->name,
                'description' => $type->description,
                'visibility' => $type->visibility,
                'access_code' => $type->access_code,
                'min_per_order' => $type->min_per_order,
                'max_per_order' => $type->max_per_order,
                'show_remaining' => $type->show_remaining,
                'pass_fee_to_buyer' => $type->pass_fee_to_buyer,
                'questions' => $type->questions,
                'position' => $type->position,
            ]);

            $type->batches->each(fn (TicketBatch $batch) => TicketBatch::create([
                'edition_id' => $targetEditionId,
                'ticket_type_id' => $copy->id,
                'name' => $batch->name,
                'position' => $batch->position,
                'price_cents' => $batch->price_cents,
                'quantity' => $batch->quantity,
                'on_sell_out' => $batch->on_sell_out,
            ]));
        });
    }
}
