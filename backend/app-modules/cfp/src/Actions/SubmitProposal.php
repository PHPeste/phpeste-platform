<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Enums\ProposalOrigin;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Events\ProposalSubmitted;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\ProposalSpeaker;
use PHPeste\Cfp\Proposals\CfpWindow;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\EmailTemplate;

class SubmitProposal
{
    public function __construct(
        private readonly CfpWindow $window,
        private readonly SpeakerProfiles $profiles,
        private readonly UserAccounts $accounts,
        private readonly EditionMailer $mailer,
    ) {}

    public function handle(Proposal $proposal): Proposal
    {
        $editionId = $proposal->edition_id;
        $ownerId = (string) $proposal->ownerId();

        if ($proposal->status !== ProposalStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Só um rascunho pode ser enviado.']);
        }

        $this->window->ensureOpen($editionId);

        $missing = $proposal->missingForSubmission();

        if ($missing !== []) {
            throw ValidationException::withMessages(array_fill_keys($missing, 'Preencha este campo antes de enviar.'));
        }

        if ($this->profiles->publicProfile($ownerId) === null) {
            throw ValidationException::withMessages(['speaker_profile' => 'Complete seu perfil de palestrante antes de enviar a proposta.']);
        }

        $limit = $this->window->settings($editionId)->proposals_per_person;

        DB::transaction(function () use ($proposal, $editionId, $ownerId, $limit): void {
            $submitted = ProposalSpeaker::query()
                ->where('user_id', $ownerId)
                ->where('is_owner', true)
                ->whereHas('proposal', fn (Builder $query) => $query->where('status', ProposalStatus::Submitted)->where('origin', ProposalOrigin::Cfp))
                ->lockForUpdate()
                ->count();

            if ($submitted >= $limit) {
                throw ValidationException::withMessages([
                    'limit' => sprintf('Você já enviou %d %s, o limite desta edição.', $submitted, $submitted === 1 ? 'proposta' : 'propostas'),
                ]);
            }

            $proposal->update(['status' => ProposalStatus::Submitted, 'submitted_at' => now()]);

            event(new ProposalSubmitted($editionId, $proposal->id));
        });

        $this->confirm($proposal);

        return $proposal->load('speakers');
    }

    private function confirm(Proposal $proposal): void
    {
        $profiles = $this->accounts->profiles(array_values($proposal->speakers->pluck('user_id')->map(fn (mixed $id): string => (string) $id)->all()));

        foreach ($profiles as $profile) {
            $this->mailer->send($proposal->edition_id, EmailTemplate::ProposalReceived, $profile['email'], $profile['name'], [
                'nome' => $profile['name'],
                'proposta' => $proposal->title,
            ]);
        }
    }
}
