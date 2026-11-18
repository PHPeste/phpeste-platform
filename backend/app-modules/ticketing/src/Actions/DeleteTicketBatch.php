<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketBatch;

class DeleteTicketBatch
{
    public function handle(TicketBatch $batch): void
    {
        if ($batch->allocated > 0) {
            throw ValidationException::withMessages(['batch' => 'Este lote já tem ingressos vendidos ou reservados. Encerre o lote em vez de remover.']);
        }

        $batch->delete();
    }
}
