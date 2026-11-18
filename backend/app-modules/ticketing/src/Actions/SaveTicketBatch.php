<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

class SaveTicketBatch
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(TicketType $type, ?TicketBatch $batch, array $attributes): TicketBatch
    {
        if ($batch instanceof TicketBatch && isset($attributes['quantity']) && (int) $attributes['quantity'] < $batch->allocated) {
            throw ValidationException::withMessages(['quantity' => sprintf('Este lote já tem %d ingressos vendidos ou reservados; a quantidade não pode ser menor.', $batch->allocated)]);
        }

        if (!$batch instanceof TicketBatch) {
            return TicketBatch::create([
                'position' => (int) $type->batches()->max('position') + 1,
                ...$attributes,
                'edition_id' => $type->edition_id,
                'ticket_type_id' => $type->id,
            ]);
        }

        $batch->update($attributes);

        return $batch;
    }
}
