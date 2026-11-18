<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\EditionInvitation;

class RevokeInvitation
{
    public function handle(EditionInvitation $invitation, string $actorId): void
    {
        if (!$invitation->isPending()) {
            return;
        }

        DB::transaction(function () use ($invitation, $actorId): void {
            $invitation->update(['revoked_at' => now()]);

            event(new TeamChanged($invitation->edition_id, TeamChanged::INVITATION_REVOKED, $actorId, 'invitation', $invitation->id, [
                'email' => $invitation->email,
            ]));
        });
    }
}
