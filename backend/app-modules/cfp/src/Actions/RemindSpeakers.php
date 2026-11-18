<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Confirmations\SpeakerMessenger;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Notifications\Enums\EmailTemplate;

class RemindSpeakers
{
    public const int MIN_HOURS_BETWEEN = 12;

    public function __construct(private readonly SpeakerMessenger $messenger) {}

    /**
     * @return array{reminded: int, skipped: int}
     */
    public function handle(?string $proposalId): array
    {
        $pending = SpeakerConfirmation::query()
            ->where('status', ConfirmationStatus::Pending)
            ->when($proposalId !== null, fn ($query) => $query->where('proposal_id', $proposalId))
            ->with('proposal.speakers')
            ->get();

        $reminded = 0;
        $skipped = 0;

        foreach ($pending as $confirmation) {
            if ($confirmation->reminded_at?->gt(now()->subHours(self::MIN_HOURS_BETWEEN))) {
                $skipped++;

                continue;
            }

            $this->messenger->send($confirmation->proposal, EmailTemplate::SpeakerConfirmationReminder, $confirmation->deadline_at);
            $confirmation->update(['reminded_at' => now()]);
            $reminded++;
        }

        return ['reminded' => $reminded, 'skipped' => $skipped];
    }
}
