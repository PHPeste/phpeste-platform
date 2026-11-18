<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Decisions;

use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Cfp\Reviews\EvaluationBoard;
use PHPeste\Identity\Contracts\SpeakerProfiles;

class DecisionBoard
{
    public function __construct(
        private readonly EvaluationBoard $evaluation,
        private readonly SpeakerProfiles $profiles,
    ) {}

    /**
     * @return list<array{track: array{id: string|null, name: string, slots: int}, proposals: list<array{proposal: Proposal, final_score: float|null, rank: int, suggested: ProposalDecision, tie_at_cutoff: bool}>}>
     */
    public function ranking(string $editionId): array
    {
        $rows = $this->evaluation->build($editionId)['rows'];
        $tracks = CfpTrack::query()->forEdition($editionId)->orderBy('position')->get()->keyBy('id');
        $reserved = Proposal::invitedHoldingSlots($editionId);
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row['proposal']->track_id ?? ''][] = $row;
        }

        $result = [];

        foreach ($groups as $trackId => $trackRows) {
            usort($trackRows, fn (array $a, array $b): int => [$b['final_score'] ?? -1, $a['proposal']->number] <=> [$a['final_score'] ?? -1, $b['proposal']->number]);

            $track = $tracks->get($trackId);
            $slots = $track instanceof CfpTrack ? max(0, $track->slots - ($reserved[$track->id] ?? 0)) : 0;
            $cutoffScore = isset($trackRows[$slots - 1], $trackRows[$slots]) ? $trackRows[$slots - 1]['final_score'] : null;

            $proposals = [];

            foreach ($trackRows as $index => $row) {
                $tieAtCutoff = $cutoffScore !== null && $row['final_score'] === $cutoffScore && $trackRows[$slots]['final_score'] === $cutoffScore;

                $proposals[] = [
                    'proposal' => $row['proposal'],
                    'final_score' => $row['final_score'],
                    'rank' => $index + 1,
                    'suggested' => $index < $slots && !$tieAtCutoff && $row['final_score'] !== null ? ProposalDecision::Accepted : ProposalDecision::Waitlisted,
                    'tie_at_cutoff' => $tieAtCutoff,
                ];
            }

            $result[] = [
                'track' => ['id' => $track?->id, 'name' => $track->name ?? 'Sem trilha', 'slots' => $slots, 'reserved' => $track instanceof CfpTrack ? ($reserved[$track->id] ?? 0) : 0],
                'proposals' => $proposals,
            ];
        }

        usort($result, fn (array $a, array $b): int => ($tracks->get((string) $a['track']['id'])->position ?? PHP_INT_MAX) <=> ($tracks->get((string) $b['track']['id'])->position ?? PHP_INT_MAX));

        return $result;
    }

    /**
     * @param  array<string, ProposalDecision|null>  $changes
     * @return array<string, mixed>
     */
    public function simulate(string $editionId, array $changes): array
    {
        $proposals = Proposal::query()->forEdition($editionId)->where('status', 'submitted')->with('speakers')->get();
        $settings = CfpSetting::query()->forEdition($editionId)->first();
        $tracks = CfpTrack::query()->forEdition($editionId)->orderBy('position')->get();

        $decisions = [];

        foreach ($proposals as $proposal) {
            $decisions[$proposal->id] = array_key_exists($proposal->id, $changes) ? $changes[$proposal->id] : $proposal->decision;
        }

        $accepted = $proposals->filter(fn (Proposal $proposal): bool => $decisions[$proposal->id] === ProposalDecision::Accepted);
        $people = array_values(array_unique($accepted->flatMap(fn (Proposal $proposal) => $proposal->speakers->map(fn (ProposalSpeaker $speaker): string => $speaker->user_id))->all()));
        $coverage = CoverageCalculator::calculate($this->profiles->states($people));

        $quota = $settings->nordeste_quota_percent ?? 50;
        $requireQuota = $settings->require_nordeste_quota ?? true;

        $byTrack = array_values($tracks->map(fn (CfpTrack $track): array => [
            'id' => $track->id,
            'name' => $track->name,
            'slots' => $track->slots,
            'accepted' => $accepted->where('track_id', $track->id)->count(),
        ])->all());

        $alerts = [];

        if ($requireQuota && $coverage['people'] > 0 && ($coverage['nordeste_percent'] ?? 0) < $quota) {
            $alerts[] = ['type' => 'nordeste_quota', 'message' => sprintf('Palestrantes do Nordeste: %d%%, abaixo da meta de %d%%.', $coverage['nordeste_percent'], $quota)];
        }

        if ($coverage['people'] > 0 && $coverage['nordeste_states_missing'] !== []) {
            $alerts[] = ['type' => 'states_missing', 'message' => 'Estados do Nordeste sem representante: '.implode(', ', $coverage['nordeste_states_missing']).'.'];
        }

        foreach ($byTrack as $track) {
            if ($track['accepted'] > $track['slots']) {
                $alerts[] = ['type' => 'track_over_slots', 'message' => sprintf('%s: %d aprovadas para %d vagas.', $track['name'], $track['accepted'], $track['slots'])];
            }
        }

        if ($coverage['unknown_state'] > 0) {
            $alerts[] = ['type' => 'unknown_state', 'message' => sprintf('%d %s sem estado no perfil, fora do cálculo do Nordeste.', $coverage['unknown_state'], $coverage['unknown_state'] === 1 ? 'pessoa aprovada' : 'pessoas aprovadas')];
        }

        $counts = array_count_values(array_map(fn (?ProposalDecision $decision): string => $decision->value ?? 'undecided', $decisions));

        return [
            'decisions' => [
                'accepted' => $counts['accepted'] ?? 0,
                'waitlisted' => $counts['waitlisted'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
                'undecided' => $counts['undecided'] ?? 0,
            ],
            'tracks' => $byTrack,
            'coverage' => [...$coverage, 'nordeste_quota_percent' => $quota, 'require_nordeste_quota' => $requireQuota],
            'alerts' => $alerts,
        ];
    }
}
