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
}
