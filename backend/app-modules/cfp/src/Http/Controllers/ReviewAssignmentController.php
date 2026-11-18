<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\AssignReviewer;
use PHPeste\Cfp\Actions\AutoAssignReviewers;
use PHPeste\Cfp\Actions\UnassignReviewer;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Http\Requests\AssignReviewerRequest;
use PHPeste\Cfp\Http\Requests\AutoAssignRequest;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;
use PHPeste\Cfp\Models\ReviewAssignment;
use PHPeste\Cfp\Reviews\Reviewers;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;

class ReviewAssignmentController
{
    public function reviewers(Reviewers $reviewers, EditionMemberships $memberships, CurrentEdition $currentEdition): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $editionId = (string) $currentEdition->id();
        $ids = $memberships->usersWith($editionId, EditionPermission::ReviewProposals);
        $names = $reviewers->names($ids);
        $assigned = ReviewAssignment::query()->whereIn('reviewer_id', $ids)->toBase()->selectRaw('reviewer_id, count(*) as total')->groupBy('reviewer_id')->pluck('total', 'reviewer_id');
        $submitted = Review::query()->whereIn('reviewer_id', $ids)->where('status', Review::SUBMITTED)->toBase()->selectRaw('reviewer_id, count(*) as total')->groupBy('reviewer_id')->pluck('total', 'reviewer_id');

        $items = array_map(fn (string $id): array => [
            'id' => $id,
            'name' => $names[$id] ?? null,
            'assigned' => (int) ($assigned[$id] ?? 0),
            'submitted' => (int) ($submitted[$id] ?? 0),
        ], $ids);

        usort($items, fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

        return response()->json(['data' => $items]);
    }

    public function index(Request $request, Reviewers $reviewers): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $assignments = $this->proposal($request)->assignments()->get();
        $names = $reviewers->names(array_values($assignments->map(fn (ReviewAssignment $assignment): string => $assignment->reviewer_id)->all()));

        return response()->json([
            'data' => array_values($assignments->map(fn (ReviewAssignment $assignment): array => [
                'reviewer_id' => $assignment->reviewer_id,
                'name' => $names[$assignment->reviewer_id] ?? null,
            ])->all()),
        ]);
    }

    public function store(AssignReviewerRequest $request, AssignReviewer $assign): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $assignment = $assign->handle($this->proposal($request), $request->string('reviewer_id')->toString(), $this->userId($request));

        return response()->json(['data' => ['reviewer_id' => $assignment->reviewer_id, 'proposal_id' => $assignment->proposal_id]], 201);
    }

    public function destroy(Request $request, UnassignReviewer $unassign): Response
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $reviewerId = $request->route('reviewer');

        abort_unless(is_string($reviewerId), 404);

        $unassign->handle($this->proposal($request), $reviewerId);

        return response()->noContent();
    }

    public function auto(AutoAssignRequest $request, AutoAssignReviewers $autoAssign, CurrentEdition $currentEdition): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => $autoAssign->handle((string) $currentEdition->id(), $request->integer('reviewers_per_proposal'), $this->userId($request))]);
    }

    private function proposal(Request $request): Proposal
    {
        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        return Proposal::query()->with('speakers')->where('status', ProposalStatus::Submitted)->whereKey($id)->firstOrFail();
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
