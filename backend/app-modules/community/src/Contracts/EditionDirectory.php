<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionDirectory
{
    public function isPublished(string $editionId): bool;

    /**
     * @return array{id: string, name: string, starts_on: string|null, ends_on: string|null, city: string|null, state: string|null, timezone: string}|null
     */
    public function summary(string $editionId): ?array;
}
