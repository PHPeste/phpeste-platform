<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionPrimaryHosts
{
    public function primaryHost(string $editionId): ?string;
}
