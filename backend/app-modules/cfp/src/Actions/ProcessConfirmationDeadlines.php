<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Confirmations\SpeakerMessenger;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Events\SpeakerConfirmationChanged;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Notifications\Enums\EmailTemplate;

class ProcessConfirmationDeadlines
{
    public const int REMIND_HOURS_BEFORE = 48;

    public function __construct(private readonly SpeakerMessenger $messenger) {}

    /**
     * @return array{reminded: int, expired: int}
     */
    public function handle(): array
    {
        $reminded = 0;
        $expired = 0;

        $pending = SpeakerConfirmation::query()
            ->withoutGlobalScopes()
            ->where('status', ConfirmationStatus::Pending)
            ->with(['proposal' => fn ($query) => $query->withoutGlobalScopes()->with(['speakers' => fn ($speakers) => $speakers->withoutGlobalScopes()])])
            ->get();

        foreach ($pending as $confirmation) {
            if ($confirmation->deadline_at->isPast()) {
                $confirmation->update(['status' => ConfirmationStatus::NoResponse]);
                event(new SpeakerConfirmationChanged($confirmation->edition_id, $confirmation->proposal_id, ConfirmationStatus::Pending, ConfirmationStatus::NoResponse, null));
                $expired++;

                continue;
            }

            if ($confirmation->reminded_at === null && $confirmation->deadline_at->lte(now()->addHours(self::REMIND_HOURS_BEFORE))) {
                $this->messenger->send($confirmation->proposal, EmailTemplate::SpeakerConfirmationReminder, $confirmation->deadline_at);
                $confirmation->update(['reminded_at' => now()]);
                $reminded++;
            }
        }

        return ['reminded' => $reminded, 'expired' => $expired];
    }
}
