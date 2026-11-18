<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\TicketType;

class TicketTypeResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->resource;

        if (!$type instanceof TicketType) {
            return [];
        }

        $current = $type->currentBatch();

        return [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'visibility' => $type->visibility->value,
            'visibility_label' => $type->visibility->label(),
            'access_code' => $type->access_code,
            'min_per_order' => $type->min_per_order,
            'max_per_order' => $type->max_per_order,
            'show_remaining' => $type->show_remaining,
            'questions' => $type->questions ?? [],
            'position' => $type->position,
            'allocated' => $type->allocated(),
            'quantity' => (int) $type->batches->sum('quantity'),
            'current_batch_id' => $current?->id,
            'batches' => TicketBatchResource::collection($type->batches)->resolve($request),
        ];
    }
}
