<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

class EloquentEditionDirectory implements EditionDirectory
{
    public function isPublished(string $editionId): bool
    {
        return Edition::query()
            ->whereKey($editionId)
            ->where('status', '!=', EditionStatus::Draft)
            ->exists();
    }

    public function summary(string $editionId): ?array
    {
        $edition = Edition::query()->find($editionId);

        if (!$edition instanceof Edition) {
            return null;
        }

        return [
            'id' => $edition->id,
            'name' => $edition->name,
            'starts_on' => $edition->starts_on?->toDateString(),
            'ends_on' => $edition->ends_on?->toDateString(),
            'city' => $edition->city,
            'state' => $edition->state?->value,
            'timezone' => $edition->timezone,
        ];
    }
}
