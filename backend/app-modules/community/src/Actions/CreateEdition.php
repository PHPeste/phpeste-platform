<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;

class CreateEdition
{
    /**
     * @param  array{name: string, slug: string, starts_on?: string|null, ends_on?: string|null, city?: string|null, state?: string|null, timezone?: string}  $attributes
     */
    public function handle(Community $community, array $attributes, string $organizerId): Edition
    {
        return DB::transaction(function () use ($community, $attributes, $organizerId): Edition {
            $edition = $community->editions()->create([
                ...$attributes,
                'status' => EditionStatus::Draft,
            ]);

            $edition->members()->create([
                'user_id' => $organizerId,
                'role' => EditionRole::Organizer,
            ]);

            return $edition;
        });
    }
}
