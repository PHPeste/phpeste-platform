<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;
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

    public function roleIn(string $editionId, string $userId): ?EditionRole
    {
        return EditionMember::query()
            ->where('edition_id', $editionId)
            ->where('user_id', $userId)
            ->first()
            ?->role;
    }

    public function can(string $editionId, string $userId, EditionPermission $permission): bool
    {
        return $this->roleIn($editionId, $userId)?->can($permission) ?? false;
    }

    public function usersWith(string $editionId, EditionPermission $permission): array
    {
        return array_values(EditionMember::query()
            ->where('edition_id', $editionId)
            ->orderBy('user_id')
            ->get()
            ->filter(fn (EditionMember $member): bool => $member->role->can($permission))
            ->map(fn (EditionMember $member): string => $member->user_id)
            ->all());
    }
}
