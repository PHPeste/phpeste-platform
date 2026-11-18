<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Models\Review;

class EloquentReviewLedger implements ReviewLedger
{
    public function submittedReviewsCount(string $editionId): int
    {
        return Review::query()->forEdition($editionId)->where('status', Review::SUBMITTED)->count();
    }
}
