<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketType;

class DeleteTicketType
{
    public function handle(TicketType $type): void
    {
        if ($type->batches()->where('allocated', '>', 0)->exists()) {
            throw ValidationException::withMessages(['ticket_type' => 'Este ingresso já tem vendas ou reservas. Encerre os lotes em vez de remover.']);
        }

        $type->delete();
    }
}
