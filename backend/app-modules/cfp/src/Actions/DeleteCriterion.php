<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Models\CfpCriterion;

class DeleteCriterion
{
    public function __construct(private readonly ReviewLedger $reviews) {}

    public function handle(CfpCriterion $criterion, bool $confirmed): void
    {
        SaveCriterion::ensureConfirmed($this->reviews->submittedReviewsCount($criterion->edition_id), $confirmed);

        $criterion->delete();
    }
}
