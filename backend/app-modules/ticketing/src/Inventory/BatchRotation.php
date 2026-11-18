<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Inventory;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use PHPeste\Ticketing\Enums\SellOutBehavior;
use PHPeste\Ticketing\Models\TicketBatch;

class BatchRotation
{
    public function run(?Carbon $at = null): int
    {
        $at ??= now();
        $closed = 0;

        $finished = TicketBatch::query()
            ->withoutGlobalScopes()
            ->whereNull('closed_at')
            ->where(fn (Builder $query) => $query->whereColumn('allocated', '>=', 'quantity')->orWhere('ends_at', '<=', $at))
            ->orderBy('position')
            ->get();

        foreach ($finished as $batch) {
            $closed += $this->close($batch, $at) ? 1 : 0;
        }

        return $closed;
    }

    public function close(TicketBatch $batch, ?Carbon $at = null): bool
    {
        $at ??= now();

        $updated = TicketBatch::query()->withoutGlobalScopes()->whereKey($batch->id)->whereNull('closed_at')->update(['closed_at' => $at]);

        if ($updated === 0) {
            return false;
        }

        if ($batch->on_sell_out !== SellOutBehavior::NextBatch) {
            return true;
        }

        $next = TicketBatch::query()
            ->withoutGlobalScopes()
            ->where('ticket_type_id', $batch->ticket_type_id)
            ->where('position', '>', $batch->position)
            ->whereNull('closed_at')
            ->orderBy('position')
            ->first();

        if ($next instanceof TicketBatch && (!$next->opensAt() instanceof Carbon || $next->opensAt()->gt($at)) && ($next->ends_at === null || $next->ends_at->gt($at))) {
            $next->update(['opened_at' => $at]);
        }

        return true;
    }
}
