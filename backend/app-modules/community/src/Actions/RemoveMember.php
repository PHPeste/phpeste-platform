<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\EditionMember;

class RemoveMember
{
    public function handle(EditionMember $member, string $actorId): void
    {
        DB::transaction(function () use ($member, $actorId): void {
            if ($member->role === EditionRole::Organizer) {
                ChangeMemberRole::ensureAnotherOrganizer($member);
            }

            $member->delete();

            event(new TeamChanged($member->edition_id, TeamChanged::REMOVED, $actorId, 'member', $member->id, [
                'user_id' => $member->user_id,
                'role' => $member->role->value,
            ]));
        });
    }
}
