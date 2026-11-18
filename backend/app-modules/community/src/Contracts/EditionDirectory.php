<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionDirectory
{
    public function isPublished(string $editionId): bool;
}
