<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\SaveReview;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Http\Requests\SaveReviewRequest;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;
use PHPeste\Cfp\Models\ReviewScore;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Cfp\Proposals\ProposalPresenter;
use PHPeste\Cfp\Reviews\Reviewers;
use PHPeste\Cfp\Reviews\ScoreCalculator;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;

class ReviewController
{
    public function __construct(
        private readonly ProposalPresenter $presenter,
        private readonly CfpWindow $window,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function queue(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ReviewProposals->value);

        $userId = $this->userId($request);
        $proposals = Proposal::query()
            ->where('status', ProposalStatus::Submitted)
            ->whereHas('assignments', fn (Builder $query) => $query->where('reviewer_id', $userId))
            ->with(['speakers', 'track', 'reviews' => fn ($query) => $query->where('reviewer_id', $userId)])
            ->orderBy('number')
            ->get();

        $order = ['pending' => 0, Review::DRAFT => 1, Review::SUBMITTED => 2];
        $presented = $this->presenter->present($proposals, $this->revealSpeakers());

        $items = [];

        foreach ($proposals as $index => $proposal) {
            $status = $proposal->reviews->first()->status ?? 'pending';
            $items[] = ['sort' => [$order[$status] ?? 0, $proposal->number], 'item' => [...$presented[$index]->resolve($request), 'my_review_status' => $status]];
        }

        usort($items, fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return response()->json(['data' => array_column($items, 'item')]);
    }

    public function show(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ReviewProposals->value);

        $proposal = $this->proposal($request);
        $review = $proposal->reviews()->with('scores')->where('reviewer_id', $this->userId($request))->first();

        return response()->json([
            'data' => [
                'proposal' => $this->presenter->one($proposal, $this->revealSpeakers())->resolve($request),
                'criteria' => $this->criteria(),
                'review' => $review === null ? null : $this->review($review),
            ],
        ]);
    }

    public function save(SaveReviewRequest $request, SaveReview $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ReviewProposals->value);

        $review = $save->handle(
            $this->proposal($request),
            $this->userId($request),
            $request->scores(),
            $request->filled('comment') ? $request->string('comment')->toString() : null,
            $request->boolean('submit'),
        );

        return response()->json(['data' => $this->review($review)]);
    }

    public function others(Request $request, Reviewers $reviewers): JsonResponse
    {
        Gate::authorize(EditionPermission::ReviewProposals->value);

        $proposal = $this->proposal($request);
        $userId = $this->userId($request);
        $isOrganizer = Gate::allows(EditionPermission::ManageProgram->value);

        if (!$isOrganizer) {
            $mine = $proposal->reviews()->where('reviewer_id', $userId)->where('status', Review::SUBMITTED)->exists();

            abort_unless($mine, 403, 'Envie a sua avaliação antes de ver as notas de outras pessoas.');
        }

        $reviews = $proposal->reviews()->with('scores')->where('status', Review::SUBMITTED)->oldest('submitted_at')->get();
        $names = $isOrganizer ? $reviewers->names(array_values($reviews->map(fn (Review $review): string => $review->reviewer_id)->all())) : [];

        $items = [];

        foreach ($reviews->values() as $index => $review) {
            $items[] = [
                ...$this->review($review),
                'reviewer' => $isOrganizer ? ($names[$review->reviewer_id] ?? null) : ($review->reviewer_id === $userId ? 'Você' : 'Revisor '.($index + 1)),
            ];
        }

        return response()->json(['data' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function review(Review $review): array
    {
        $criteria = CfpCriterion::query()->forEdition($review->edition_id)->get();

        return [
            'status' => $review->status,
            'scores' => $review->scores->mapWithKeys(fn (ReviewScore $score): array => [$score->criterion_id => $score->score])->all(),
            'comment' => $review->comment,
            'weighted_score' => ($score = ScoreCalculator::review($review, $criteria)) === null ? null : round($score, 2),
            'submitted_at' => $review->submitted_at?->clone()->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return list<array{id: string, name: string, description: string|null, weight: int}>
     */
    private function criteria(): array
    {
        return array_values(CfpCriterion::query()->orderBy('position')->get()->map(fn (CfpCriterion $criterion): array => [
            'id' => $criterion->id,
            'name' => $criterion->name,
            'description' => $criterion->description,
            'weight' => $criterion->weight,
        ])->all());
    }

    private function revealSpeakers(): bool
    {
        return Gate::allows(EditionPermission::ManageProgram->value)
            || !$this->window->settings((string) $this->currentEdition->id())->anonymous_review;
    }

    private function proposal(Request $request): Proposal
    {
        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        return Proposal::query()->with(['speakers', 'track'])->where('status', ProposalStatus::Submitted)->whereKey($id)->firstOrFail();
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
