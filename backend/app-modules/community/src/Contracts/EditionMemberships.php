<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;

interface EditionMemberships
{
    /**
     * @return list<array{edition_id: string, role: string}>
     */
    public function rolesOf(string $userId): array;

    public function roleIn(string $editionId, string $userId): ?EditionRole;

    public function can(string $editionId, string $userId, EditionPermission $permission): bool;
}
