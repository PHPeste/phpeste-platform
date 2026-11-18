<?php

declare(strict_types=1);

namespace PHPeste\Branding\Contracts;

interface EditionThemes
{
    /**
     * @return array{colors: array<string, string>, fonts: array{heading: string, body: string}, radius: int, logos: array<string, string|null>}
     */
    public function published(string $editionId): array;
}
