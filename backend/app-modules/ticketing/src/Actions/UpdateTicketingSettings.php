<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketingSetting;

class UpdateTicketingSettings
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $editionId, array $attributes): TicketingSetting
    {
        $settings = TicketingSetting::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId]);
        $capacity = $attributes['capacity'] ?? null;

        if (is_int($capacity) && $capacity < $settings->allocated) {
            throw ValidationException::withMessages(['capacity' => sprintf('Já há %d ingressos vendidos ou reservados; a capacidade não pode ser menor.', $settings->allocated)]);
        }

        $settings->update($attributes);

        return $settings;
    }
}
