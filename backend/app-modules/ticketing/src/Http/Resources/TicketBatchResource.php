<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\TicketBatch;

class TicketBatchResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $batch = $this->resource;

        if (!$batch instanceof TicketBatch) {
            return [];
        }

        $status = $batch->status();

        return [
            'id' => $batch->id,
            'ticket_type_id' => $batch->ticket_type_id,
            'name' => $batch->name,
            'position' => $batch->position,
            'price' => $this->money($batch->price_cents),
            'quantity' => $batch->quantity,
            'allocated' => $batch->allocated,
            'remaining' => $batch->remaining(),
            'starts_at' => $this->isoDate($batch->starts_at),
            'ends_at' => $this->isoDate($batch->ends_at),
            'opens_at' => $this->isoDate($batch->opensAt()),
            'on_sell_out' => $batch->on_sell_out->value,
            'status' => $status->value,
            'status_label' => $status->label(),
            'closed_at' => $this->isoDate($batch->closed_at),
        ];
    }
}
