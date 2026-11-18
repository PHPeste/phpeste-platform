<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\TicketVisibility;
use PHPeste\Ticketing\Models\TicketType;

class SaveTicketType
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $editionId, ?TicketType $type, array $attributes): TicketType
    {
        $visibility = isset($attributes['visibility']) ? TicketVisibility::from((string) $attributes['visibility']) : ($type->visibility ?? TicketVisibility::Public);

        $min = (int) ($attributes['min_per_order'] ?? $type->min_per_order ?? 1);
        $max = (int) ($attributes['max_per_order'] ?? $type->max_per_order ?? 10);

        if ($max < $min) {
            throw ValidationException::withMessages(['max_per_order' => 'O máximo por pedido precisa ser maior ou igual ao mínimo.']);
        }

        if ($visibility !== TicketVisibility::Code) {
            $attributes['access_code'] = null;
        }

        if (!$type instanceof TicketType) {
            return TicketType::create([
                'position' => (int) TicketType::query()->forEdition($editionId)->max('position') + 1,
                ...$attributes,
                'edition_id' => $editionId,
            ]);
        }

        $type->update($attributes);

        return $type;
    }
}
