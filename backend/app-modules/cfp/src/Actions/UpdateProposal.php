<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;

class UpdateProposal
{
    public function __construct(private readonly CfpWindow $window) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Proposal $proposal, array $attributes, bool $byOrganization): Proposal
    {
        if ($proposal->status === ProposalStatus::Withdrawn) {
            throw ValidationException::withMessages(['status' => 'Esta proposta foi retirada e não pode mais ser alterada.']);
        }

        if (!$byOrganization) {
            $this->window->ensureEditable($proposal->edition_id);
        }

        $proposal->update($attributes);

        if ($proposal->status === ProposalStatus::Submitted && $proposal->missingForSubmission() !== []) {
            throw ValidationException::withMessages(['proposal' => 'Uma proposta enviada precisa continuar com todos os campos preenchidos.']);
        }

        return $proposal->load('speakers');
    }
}
