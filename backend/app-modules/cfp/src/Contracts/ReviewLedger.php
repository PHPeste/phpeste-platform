<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Contracts;

interface ReviewLedger
{
    public function submittedReviewsCount(string $editionId): int;
}
