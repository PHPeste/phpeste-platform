<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Overview;

use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Tasks\Contracts\TaskLinkProvider;

/**
 * Deixa uma tarefa apontar para um acordo de patrocínio desta edição.
 */
class SponsorshipTaskLinks implements TaskLinkProvider
{
    public function key(): string
    {
        return 'sponsorship';
    }

    public function label(): string
    {
        return 'Patrocinador';
    }

    public function names(string $editionId, array $ids): array
    {
        return Sponsorship::query()->forEdition($editionId)->with(['sponsor:id,name', 'tier:id,name'])->whereKey($ids)->get()
            ->mapWithKeys(fn (Sponsorship $deal): array => [$deal->id => $this->name($deal)])->all();
    }

    public function options(string $editionId): array
    {
        return array_values(Sponsorship::query()->forEdition($editionId)->with(['sponsor:id,name', 'tier:id,name'])->get()
            ->map(fn (Sponsorship $deal): array => ['id' => $deal->id, 'name' => $this->name($deal)])
            ->sortBy('name')->all());
    }

    private function name(Sponsorship $deal): string
    {
        return $deal->tier === null ? $deal->sponsor->name : sprintf('%s (%s)', $deal->sponsor->name, $deal->tier->name);
    }
}
