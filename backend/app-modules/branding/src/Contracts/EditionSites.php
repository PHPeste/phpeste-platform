<?php

declare(strict_types=1);

namespace PHPeste\Branding\Contracts;

interface EditionSites
{
    public function url(string $editionId): string;
}
