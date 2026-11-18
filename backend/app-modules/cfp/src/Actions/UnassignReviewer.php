<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;

class UnassignReviewer
{
    public function handle(Proposal $proposal, string $reviewerId): void
    {
        $submitted = Review::query()
            ->where('proposal_id', $proposal->id)
            ->where('reviewer_id', $reviewerId)
            ->where('status', Review::SUBMITTED)
            ->exists();

        if ($submitted) {
            throw ValidationException::withMessages(['reviewer_id' => 'Esta pessoa já enviou a avaliação; a nota não pode ser descartada.']);
        }

        $proposal->assignments()->where('reviewer_id', $reviewerId)->delete();
        Review::query()->where('proposal_id', $proposal->id)->where('reviewer_id', $reviewerId)->delete();
    }
}
