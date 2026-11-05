<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

abstract class ApiResource extends JsonResource
{
    /**
     * @return array{amount: int, currency: string}|null
     */
    protected function money(?int $cents, string $currency = 'BRL'): ?array
    {
        if ($cents === null) {
            return null;
        }

        return ['amount' => $cents, 'currency' => $currency];
    }

    protected function isoDate(?Carbon $date): ?string
    {
        return $date?->clone()->utc()->toIso8601ZuluString();
    }
}
