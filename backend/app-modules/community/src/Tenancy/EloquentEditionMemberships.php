<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Models\EditionMember;

class EloquentEditionMemberships implements EditionMemberships
{
    public function rolesOf(string $userId): array
    {
        return array_values(EditionMember::query()
            ->where('user_id', $userId)
            ->oldest()
            ->get()
            ->map(fn (EditionMember $member): array => [
                'edition_id' => $member->edition_id,
                'role' => $member->role->value,
            ])
            ->all());
    }
}
