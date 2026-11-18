<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionMemberships
{
    /**
     * @return list<array{edition_id: string, role: string}>
     */
    public function rolesOf(string $userId): array;
}
