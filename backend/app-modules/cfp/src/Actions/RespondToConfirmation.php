<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Events\SpeakerConfirmationChanged;
use PHPeste\Cfp\Models\SpeakerConfirmation;

class RespondToConfirmation
{
    public const string CONFIRM = 'confirm';

    public const string DECLINE = 'decline';

    public const string WITHDRAW = 'withdraw';

    public function handle(SpeakerConfirmation $confirmation, string $response, ?string $reason, string $actorId): SpeakerConfirmation
    {
        $from = $confirmation->status;
        $awaiting = in_array($from, [ConfirmationStatus::Pending, ConfirmationStatus::NoResponse], true);

        $to = match (true) {
            $response === self::CONFIRM && $awaiting => ConfirmationStatus::Confirmed,
            $response === self::DECLINE && $awaiting => ConfirmationStatus::Declined,
            $response === self::WITHDRAW && $from === ConfirmationStatus::Confirmed => ConfirmationStatus::Withdrawn,
            default => throw ValidationException::withMessages([
                'confirmation' => sprintf('Não é possível fazer isso com a participação em "%s".', $from->label()),
            ]),
        };

        DB::transaction(function () use ($confirmation, $from, $to, $reason, $actorId): void {
            $confirmation->update(['status' => $to, 'responded_at' => now(), 'reason' => $reason]);

            event(new SpeakerConfirmationChanged($confirmation->edition_id, $confirmation->proposal_id, $from, $to, $actorId));
        });

        return $confirmation;
    }
}
