<?php

declare(strict_types=1);

namespace PHPeste\Community\Cloning;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Models\EditionMember;

class TeamCloner implements EditionCloner
{
    public function key(): string
    {
        return 'team';
    }

    public function label(): string
    {
        return 'Equipe e papéis';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        $existing = EditionMember::query()->where('edition_id', $targetEditionId)->pluck('user_id')->all();

        EditionMember::query()
            ->where('edition_id', $sourceEditionId)
            ->whereNotIn('user_id', $existing)
            ->get()
            ->each(fn (EditionMember $member) => EditionMember::create([
                'edition_id' => $targetEditionId,
                'user_id' => $member->user_id,
                'role' => $member->role,
            ]));
    }
}
