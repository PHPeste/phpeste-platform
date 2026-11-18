<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;

class CreateProposal
{
    public function __construct(private readonly CfpWindow $window) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $editionId, string $ownerId, array $attributes): Proposal
    {
        $this->window->ensureEditable($editionId);

        return DB::transaction(function () use ($editionId, $ownerId, $attributes): Proposal {
            $number = (int) Proposal::query()->forEdition($editionId)->lockForUpdate()->max('number') + 1;

            $proposal = Proposal::create([
                ...$attributes,
                'edition_id' => $editionId,
                'number' => $number,
                'status' => ProposalStatus::Draft,
            ]);

            $proposal->speakers()->create(['edition_id' => $editionId, 'user_id' => $ownerId, 'is_owner' => true]);

            return $proposal->load('speakers');
        });
    }
}
