<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Models\SpeakerConfirmation;

class MarkSpeakerAttendance
{
    public function handle(string $userId, bool $present): int
    {
        $confirmations = SpeakerConfirmation::query()
            ->where('status', ConfirmationStatus::Confirmed)
            ->whereHas('proposal.speakers', fn (Builder $query) => $query->where('user_id', $userId))
            ->get();

        if ($confirmations->isEmpty()) {
            throw ValidationException::withMessages(['present' => 'Só dá para marcar presença de quem tem palestra confirmada.']);
        }

        foreach ($confirmations as $confirmation) {
            $confirmation->update(['attended_at' => $present ? ($confirmation->attended_at ?? now()) : null]);
        }

        return $confirmations->count();
    }
}
