<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Events\TeamChanged;
use PHPeste\Community\Models\EditionMember;

class ChangeMemberRole
{
    public static function ensureAnotherOrganizer(EditionMember $member): void
    {
        $others = EditionMember::query()
            ->where('edition_id', $member->edition_id)
            ->where('role', EditionRole::Organizer)
            ->whereKeyNot($member->id)
            ->lockForUpdate()
            ->count();

        if ($others === 0) {
            throw ValidationException::withMessages([
                'role' => 'A edição precisa de pelo menos uma pessoa organizadora.',
            ]);
        }
    }

    public function handle(EditionMember $member, EditionRole $role, string $actorId): EditionMember
    {
        if ($member->role === $role) {
            return $member;
        }

        DB::transaction(function () use ($member, $role, $actorId): void {
            $previous = $member->role;

            if ($previous === EditionRole::Organizer) {
                self::ensureAnotherOrganizer($member);
            }

            $member->update(['role' => $role]);

            event(new TeamChanged($member->edition_id, TeamChanged::ROLE_CHANGED, $actorId, 'member', $member->id, [
                'user_id' => $member->user_id,
                'from' => $previous->value,
                'to' => $role->value,
            ]));
        });

        return $member;
    }
}
