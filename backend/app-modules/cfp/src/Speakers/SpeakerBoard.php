<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Speakers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PHPeste\Cfp\Decisions\CoverageCalculator;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Contracts\UserAccounts;

final readonly class SpeakerBoard
{
    public const string WAITLISTED = 'waitlisted';

    public function __construct(
        private UserAccounts $accounts,
        private SpeakerProfiles $profiles,
        private CfpWindow $window,
    ) {}

    public static function status(Proposal $proposal): string
    {
        if ($proposal->decision === ProposalDecision::Waitlisted) {
            return self::WAITLISTED;
        }

        return $proposal->confirmation->status->value ?? ConfirmationStatus::Pending->value;
    }

    public static function statusLabel(Proposal $proposal): string
    {
        return $proposal->decision === ProposalDecision::Waitlisted
            ? ProposalDecision::Waitlisted->label()
            : ($proposal->confirmation->status ?? ConfirmationStatus::Pending)->label();
    }

    /**
     * @return Collection<int, Proposal>
     */
    public function proposals(string $editionId, ?string $userId = null): Collection
    {
        return Proposal::query()
            ->forEdition($editionId)
            ->whereIn('decision', [ProposalDecision::Accepted, ProposalDecision::Waitlisted])
            ->when($userId !== null, fn (Builder $query) => $query->whereHas('speakers', fn (Builder $speakers) => $speakers->where('user_id', $userId)))
            ->with(['speakers', 'track', 'confirmation'])
            ->orderBy('number')
            ->get();
    }

    /**
     * @return array{summary: array<string, int|null>, coverage: list<array{state: string, confirmed: int}>, rows: list<array<string, mixed>>}
     */
    public function build(string $editionId): array
    {
        $proposals = $this->proposals($editionId);
        $userIds = array_values(array_unique($proposals->flatMap(fn (Proposal $proposal) => $proposal->speakers->pluck('user_id'))->map(fn (mixed $id): string => (string) $id)->all()));
        $accounts = $this->accounts->profiles($userIds);
        $states = $this->profiles->states($userIds);

        $rows = [];
        $statusByPerson = [];

        foreach ($proposals as $proposal) {
            $status = self::status($proposal);

            foreach ($proposal->speakers as $speaker) {
                $state = $states[$speaker->user_id] ?? null;
                $statusByPerson[$speaker->user_id][] = $status;
                $rows[] = [
                    'user_id' => $speaker->user_id,
                    'name' => $accounts[$speaker->user_id]['name'] ?? null,
                    'email' => $accounts[$speaker->user_id]['email'] ?? null,
                    'state' => $state,
                    'nordeste' => BrazilianState::tryFrom((string) $state)?->isNordeste() ?? false,
                    'proposal' => ['id' => $proposal->id, 'number' => $proposal->number, 'title' => $proposal->title, 'track' => $proposal->track?->name],
                    'status' => $status,
                    'status_label' => self::statusLabel($proposal),
                    'deadline_at' => $proposal->confirmation?->deadline_at->clone()->utc()->toIso8601ZuluString(),
                    'reminded_at' => $proposal->confirmation?->reminded_at?->clone()->utc()->toIso8601ZuluString(),
                    'attended_at' => $proposal->confirmation?->attended_at?->clone()->utc()->toIso8601ZuluString(),
                ];
            }
        }

        $confirmed = array_keys(array_filter($statusByPerson, fn (array $statuses): bool => in_array(ConfirmationStatus::Confirmed->value, $statuses, true)));
        $coverage = CoverageCalculator::calculate(array_intersect_key($states, array_flip($confirmed)));

        return [
            'summary' => [
                'confirmed' => count($confirmed),
                'awaiting' => count(array_filter($statusByPerson, fn (array $statuses): bool => in_array(ConfirmationStatus::Pending->value, $statuses, true))),
                'waitlisted' => $proposals->filter(fn (Proposal $proposal): bool => $proposal->decision === ProposalDecision::Waitlisted)->count(),
                'slots' => (int) CfpTrack::query()->forEdition($editionId)->sum('slots'),
                'nordeste_percent' => $coverage['nordeste_percent'],
                'nordeste_quota_percent' => $this->window->settings($editionId)->nordeste_quota_percent,
            ],
            'coverage' => array_values(array_map(
                fn (BrazilianState $state): array => ['state' => $state->value, 'confirmed' => $coverage['by_state'][$state->value] ?? 0],
                array_filter(BrazilianState::cases(), fn (BrazilianState $state): bool => $state->isNordeste()),
            )),
            'rows' => $rows,
        ];
    }
}
