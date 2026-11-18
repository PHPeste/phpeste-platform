<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Models\CfpCriterion;

class SaveCriterion
{
    public function __construct(private readonly ReviewLedger $reviews) {}

    public static function ensureConfirmed(int $reviews, bool $confirmed): void
    {
        if ($reviews === 0 || $confirmed) {
            return;
        }

        throw ValidationException::withMessages([
            'confirm' => sprintf(
                'Já existem %d %s. Mudar os critérios recalcula a nota final de todas as propostas; as notas dadas são preservadas. Envie confirm=true para continuar.',
                $reviews,
                $reviews === 1 ? 'avaliação enviada' : 'avaliações enviadas',
            ),
        ]);
    }

    /**
     * @param  array{name?: string, description?: string|null, weight?: int, position?: int}  $attributes
     */
    public function handle(string $editionId, ?CfpCriterion $criterion, array $attributes, bool $confirmed): CfpCriterion
    {
        $changesScores = !$criterion instanceof CfpCriterion || (isset($attributes['weight']) && $attributes['weight'] !== $criterion->weight);

        if ($changesScores) {
            self::ensureConfirmed($this->reviews->submittedReviewsCount($editionId), $confirmed);
        }

        if (!$criterion instanceof CfpCriterion) {
            return CfpCriterion::create([
                'position' => (int) CfpCriterion::query()->max('position') + 1,
                ...$attributes,
            ]);
        }

        $criterion->update($attributes);

        return $criterion;
    }
}
