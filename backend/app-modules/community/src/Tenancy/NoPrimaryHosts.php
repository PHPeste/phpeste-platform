<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use PHPeste\Community\Contracts\EditionPrimaryHosts;

class NoPrimaryHosts implements EditionPrimaryHosts
{
    public function primaryHost(string $editionId): ?string
    {
        return null;
    }
}
