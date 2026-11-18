<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

use PHPeste\Community\Data\OverviewContribution;

interface EditionOverviewContributor
{
    public const string TAG = 'community.overview-contributors';

    public function contribute(string $editionId): OverviewContribution;
}
