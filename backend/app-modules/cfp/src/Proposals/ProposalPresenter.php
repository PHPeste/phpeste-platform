<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Proposals;

use Illuminate\Support\Collection;
use PHPeste\Cfp\Http\Resources\ProposalResource;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Contracts\UserAccounts;

class ProposalPresenter
{
    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly SpeakerProfiles $profiles,
    ) {}

    /**
     * @param  Collection<int, Proposal>  $proposals
     * @return list<ProposalResource>
     */
    public function present(Collection $proposals, bool $revealSpeakers): array
    {
        $userIds = array_values(array_unique($proposals->flatMap(fn (Proposal $proposal) => $proposal->speakers->pluck('user_id'))->map(fn (mixed $id): string => (string) $id)->all()));
        $accounts = $revealSpeakers ? $this->accounts->profiles($userIds) : [];
        $states = $this->profiles->states($userIds);

        return array_values($proposals->map(function (Proposal $proposal) use ($revealSpeakers, $accounts, $states): ProposalResource {
            $ownerState = $states[(string) $proposal->ownerId()] ?? null;
            $nordeste = $ownerState !== null && (BrazilianState::tryFrom($ownerState)?->isNordeste() ?? false);

            $speakers = $revealSpeakers
                ? array_values($proposal->speakers->map(fn (ProposalSpeaker $speaker): array => [
                    'user_id' => $speaker->user_id,
                    'name' => $accounts[$speaker->user_id]['name'] ?? null,
                    'email' => $accounts[$speaker->user_id]['email'] ?? null,
                    'state' => $states[$speaker->user_id] ?? null,
                    'is_owner' => $speaker->is_owner,
                ])->all())
                : null;

            return new ProposalResource($proposal, $speakers, $nordeste);
        })->all());
    }

    public function one(Proposal $proposal, bool $revealSpeakers): ProposalResource
    {
        return $this->present(new Collection([$proposal]), $revealSpeakers)[0];
    }
}
