<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketingSetting;

class UpdateTicketingSettings
{
    public function handle(string $editionId, ?int $capacity): TicketingSetting
    {
        $settings = TicketingSetting::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId]);

        if ($capacity !== null && $capacity < $settings->allocated) {
            throw ValidationException::withMessages(['capacity' => sprintf('Já há %d ingressos vendidos ou reservados; a capacidade não pode ser menor.', $settings->allocated)]);
        }

        $settings->update(['capacity' => $capacity]);

        return $settings;
    }
}
