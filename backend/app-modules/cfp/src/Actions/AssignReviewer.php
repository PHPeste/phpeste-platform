<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ReviewAssignment;
use PHPeste\Cfp\Reviews\ConflictOfInterest;
use PHPeste\Cfp\Reviews\Reviewers;

class AssignReviewer
{
    public function __construct(
        private readonly Reviewers $reviewers,
        private readonly ConflictOfInterest $conflicts,
    ) {}

    public function handle(Proposal $proposal, string $reviewerId, ?string $assignedBy): ReviewAssignment
    {
        if (!$this->reviewers->canReview($proposal->edition_id, $reviewerId)) {
            throw ValidationException::withMessages(['reviewer_id' => 'Esta pessoa não tem permissão para avaliar propostas nesta edição.']);
        }

        $conflict = $this->conflicts->reason($proposal, $reviewerId);

        if ($conflict !== null) {
            throw ValidationException::withMessages(['reviewer_id' => $conflict]);
        }

        return ReviewAssignment::query()->firstOrCreate(
            ['edition_id' => $proposal->edition_id, 'proposal_id' => $proposal->id, 'reviewer_id' => $reviewerId],
            ['assigned_by' => $assignedBy],
        );
    }
}
