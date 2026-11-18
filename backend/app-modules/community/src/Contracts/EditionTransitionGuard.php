<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

use PHPeste\Community\Enums\EditionStatus;

interface EditionTransitionGuard
{
    public const string TAG = 'community.edition-transition-guards';

    /**
     * @return list<string>
     */
    public function blockers(string $editionId, EditionStatus $next): array;
}
