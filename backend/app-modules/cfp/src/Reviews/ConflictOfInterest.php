<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use PHPeste\Cfp\Models\Proposal;
use PHPeste\Identity\Contracts\SpeakerProfiles;

class ConflictOfInterest
{
    public function __construct(private readonly SpeakerProfiles $profiles) {}

    public function reason(Proposal $proposal, string $reviewerId): ?string
    {
        if ($proposal->hasSpeaker($reviewerId)) {
            return 'Quem avalia não pode ser autor nem coautor da proposta.';
        }

        $speakerIds = array_values($proposal->speakers->map(fn ($speaker): string => $speaker->user_id)->all());
        $companies = $this->profiles->companies([...$speakerIds, $reviewerId]);
        $reviewerCompany = $companies[$reviewerId] ?? null;

        if ($reviewerCompany === null) {
            return null;
        }

        foreach ($speakerIds as $speakerId) {
            if (($companies[$speakerId] ?? null) === $reviewerCompany) {
                return 'Quem avalia trabalha na mesma empresa de quem enviou a proposta.';
            }
        }

        return null;
    }
}
