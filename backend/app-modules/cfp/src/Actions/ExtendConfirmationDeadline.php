<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Events\SpeakerConfirmationChanged;
use PHPeste\Cfp\Models\SpeakerConfirmation;

class ExtendConfirmationDeadline
{
    public function handle(SpeakerConfirmation $confirmation, Carbon $deadline, string $actorId): SpeakerConfirmation
    {
        $from = $confirmation->status;

        if (!in_array($from, [ConfirmationStatus::Pending, ConfirmationStatus::NoResponse], true)) {
            throw ValidationException::withMessages(['confirmation' => 'Só dá para estender o prazo de quem ainda não respondeu.']);
        }

        $confirmation->update(['status' => ConfirmationStatus::Pending, 'deadline_at' => $deadline, 'reminded_at' => null]);

        if ($from !== ConfirmationStatus::Pending) {
            event(new SpeakerConfirmationChanged($confirmation->edition_id, $confirmation->proposal_id, $from, ConfirmationStatus::Pending, $actorId));
        }

        return $confirmation;
    }
}
