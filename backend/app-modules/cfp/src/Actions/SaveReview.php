<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\Review;
use PHPeste\Cfp\Reviews\ConflictOfInterest;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SaveReview
{
    public function __construct(private readonly ConflictOfInterest $conflicts) {}

    /**
     * @param  array<string, int>  $scores
     */
    public function handle(Proposal $proposal, string $reviewerId, array $scores, ?string $comment, bool $submit): Review
    {
        throw_unless($proposal->assignments()->where('reviewer_id', $reviewerId)->exists(), AccessDeniedHttpException::class, 'Esta proposta não foi atribuída a você.');

        $conflict = $this->conflicts->reason($proposal, $reviewerId);

        throw_if($conflict !== null, AccessDeniedHttpException::class, $conflict);

        $review = Review::query()->firstOrNew(['proposal_id' => $proposal->id, 'reviewer_id' => $reviewerId], ['edition_id' => $proposal->edition_id]);

        if ($review->isSubmitted()) {
            throw ValidationException::withMessages(['review' => 'Esta avaliação já foi enviada e não pode mais ser alterada.']);
        }

        $criteria = CfpCriterion::query()->forEdition($proposal->edition_id)->pluck('id')->all();
        $unknown = array_diff(array_keys($scores), $criteria);

        if ($unknown !== []) {
            throw ValidationException::withMessages(['scores' => 'Há notas para critérios que não existem nesta edição.']);
        }

        if ($submit && array_diff($criteria, array_keys($scores)) !== []) {
            throw ValidationException::withMessages(['scores' => 'Dê nota para todos os critérios antes de enviar.']);
        }

        return DB::transaction(function () use ($review, $scores, $comment, $submit): Review {
            $review->fill([
                'comment' => $comment,
                'status' => $submit ? Review::SUBMITTED : Review::DRAFT,
                'submitted_at' => $submit ? now() : null,
            ])->save();

            foreach ($scores as $criterionId => $score) {
                $review->scores()->updateOrCreate(['criterion_id' => $criterionId], ['score' => $score]);
            }

            return $review->load('scores');
        });
    }
}
