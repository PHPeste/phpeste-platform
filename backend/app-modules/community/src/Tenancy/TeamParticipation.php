<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Contracts\EditionParticipation;

class TeamParticipation implements EditionParticipation
{
    public function participates(string $editionId, string $userId): bool
    {
        return EditionMember::query()->where('edition_id', $editionId)->where('user_id', $userId)->exists();
    }
}
