<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Confirmations\SpeakerMessenger;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Enums\ProposalOrigin;
use PHPeste\Cfp\Enums\ProposalStatus;
use PHPeste\Cfp\Events\SpeakerInvited;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Notifications\Enums\EmailTemplate;

class InviteSpeaker
{
    public function __construct(
        private readonly UserAccounts $accounts,
        private readonly SpeakerMessenger $messenger,
    ) {}

    /**
     * @param  array{name: string, email: string, title: string, track_id: string, format: string, summary: string|null}  $invitation
     */
    public function handle(string $editionId, array $invitation, string $actorId): Proposal
    {
        $existing = $this->accounts->findByEmail($invitation['email']);

        if ($existing !== null && $this->alreadySpeaks($editionId, $existing['id'])) {
            throw ValidationException::withMessages(['email' => 'Essa pessoa já tem palestra aprovada nesta edição.']);
        }

        $proposal = DB::transaction(function () use ($editionId, $invitation, $actorId): Proposal {
            $speaker = $this->accounts->findOrCreateInvited($invitation['name'], $invitation['email']);
            $number = (int) Proposal::query()->forEdition($editionId)->lockForUpdate()->max('number') + 1;

            $proposal = Proposal::create([
                'edition_id' => $editionId,
                'number' => $number,
                'track_id' => $invitation['track_id'],
                'title' => $invitation['title'],
                'summary' => $invitation['summary'],
                'format' => $invitation['format'],
                'status' => ProposalStatus::Submitted,
                'origin' => ProposalOrigin::Invited,
                'submitted_at' => now(),
                'decision' => ProposalDecision::Accepted,
                'decided_at' => now(),
                'decided_by' => $actorId,
            ]);
            $proposal->speakers()->create(['edition_id' => $editionId, 'user_id' => $speaker['id'], 'is_owner' => true]);

            $days = CfpSetting::query()->forEdition($editionId)->value('confirmation_days');
            SpeakerConfirmation::create([
                'edition_id' => $editionId,
                'proposal_id' => $proposal->id,
                'status' => ConfirmationStatus::Pending,
                'deadline_at' => now()->addDays(is_numeric($days) ? (int) $days : 7)->endOfDay(),
            ]);

            event(new SpeakerInvited($editionId, $proposal->id, $speaker['id'], $speaker['email'], $proposal->title, $actorId));

            return $proposal->load(['speakers', 'confirmation']);
        });

        $this->messenger->send($proposal, EmailTemplate::SpeakerInvited, $proposal->confirmation?->deadline_at);

        return $proposal;
    }

    private function alreadySpeaks(string $editionId, string $userId): bool
    {
        return Proposal::query()
            ->forEdition($editionId)
            ->where('decision', ProposalDecision::Accepted)
            ->whereHas('speakers', fn (Builder $query) => $query->where('user_id', $userId))
            ->whereDoesntHave('confirmation', fn (Builder $query) => $query->whereIn('status', [ConfirmationStatus::Declined, ConfirmationStatus::Withdrawn, ConfirmationStatus::Cancelled, ConfirmationStatus::NoResponse]))
            ->exists();
    }
}
