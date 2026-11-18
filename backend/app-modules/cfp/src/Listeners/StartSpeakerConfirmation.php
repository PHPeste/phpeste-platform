<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Listeners;

use PHPeste\Cfp\Confirmations\SpeakerMessenger;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Events\ProposalDecided;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Notifications\Enums\EmailTemplate;

class StartSpeakerConfirmation
{
    public function __construct(private readonly SpeakerMessenger $messenger) {}

    public function handle(ProposalDecided $event): void
    {
        $proposal = Proposal::query()->forEdition($event->editionId)->with('speakers')->find($event->proposalId);

        if ($proposal === null) {
            return;
        }

        $confirmation = SpeakerConfirmation::query()->forEdition($event->editionId)->where('proposal_id', $proposal->id)->first();

        if ($event->decision !== ProposalDecision::Accepted) {
            $confirmation?->update(['status' => ConfirmationStatus::Cancelled]);

            if ($event->decision === ProposalDecision::Waitlisted) {
                $this->messenger->send($proposal, EmailTemplate::CfpWaitlisted);
            }

            return;
        }

        $days = CfpSetting::query()->forEdition($event->editionId)->value('confirmation_days');
        $deadline = now()->addDays(is_numeric($days) ? (int) $days : 7)->endOfDay();

        SpeakerConfirmation::query()->forEdition($event->editionId)->updateOrCreate(
            ['edition_id' => $event->editionId, 'proposal_id' => $proposal->id],
            ['status' => ConfirmationStatus::Pending, 'deadline_at' => $deadline, 'responded_at' => null, 'reminded_at' => null, 'reason' => null],
        );

        $this->messenger->send($proposal, EmailTemplate::CfpAccepted, $deadline);
    }
}
