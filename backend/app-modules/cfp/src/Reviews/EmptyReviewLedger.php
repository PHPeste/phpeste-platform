<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use PHPeste\Cfp\Contracts\ReviewLedger;

class EmptyReviewLedger implements ReviewLedger
{
    public function submittedReviewsCount(string $editionId): int
    {
        return 0;
    }
}
