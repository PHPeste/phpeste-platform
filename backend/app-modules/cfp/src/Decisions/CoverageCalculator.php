<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Decisions;

use PHPeste\Community\Enums\BrazilianState;

final class CoverageCalculator
{
    /**
     * @param  array<string, string|null>  $statesByPerson
     * @return array{people: int, unknown_state: int, by_state: array<string, int>, nordeste_people: int, nordeste_percent: int|null, nordeste_states_missing: list<string>}
     */
    public static function calculate(array $statesByPerson): array
    {
        $byState = [];
        $unknown = 0;
        $nordeste = 0;

        foreach ($statesByPerson as $state) {
            $uf = $state === null ? null : BrazilianState::tryFrom($state);

            if ($uf === null) {
                $unknown++;

                continue;
            }

            $byState[$uf->value] = ($byState[$uf->value] ?? 0) + 1;

            if ($uf->isNordeste()) {
                $nordeste++;
            }
        }

        ksort($byState);
        $people = count($statesByPerson);

        $missing = array_values(array_map(
            fn (BrazilianState $state): string => $state->value,
            array_filter(BrazilianState::cases(), fn (BrazilianState $state): bool => $state->isNordeste() && !isset($byState[$state->value])),
        ));

        return [
            'people' => $people,
            'unknown_state' => $unknown,
            'by_state' => $byState,
            'nordeste_people' => $nordeste,
            'nordeste_percent' => $people === 0 ? null : (int) floor($nordeste * 100 / $people),
            'nordeste_states_missing' => $missing,
        ];
    }
}
