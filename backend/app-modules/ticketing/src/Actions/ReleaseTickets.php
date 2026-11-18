<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Ticketing\Data\Allocation;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;

class ReleaseTickets
{
    public function handle(Allocation $allocation): void
    {
        DB::transaction(function () use ($allocation): void {
            TicketBatch::query()->forEdition($allocation->editionId)
                ->whereKey($allocation->ticketBatchId)
                ->where('allocated', '>=', $allocation->quantity)
                ->decrement('allocated', $allocation->quantity);

            TicketingSetting::query()->forEdition($allocation->editionId)
                ->where('allocated', '>=', $allocation->quantity)
                ->decrement('allocated', $allocation->quantity);
        });
    }
}
