<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Identity\Models\User;

class SaveSpeakerProfile
{
    /**
     * @param  array{display_name: string, job_title: string|null, company: string|null, bio: string|null, city: string|null, state: string|null, links: array<string, string|null>}  $profile
     * @param  array{accessibility_needs: string|null, dietary_restrictions: string|null}  $private
     */
    public function handle(User $user, array $profile, array $private): User
    {
        DB::transaction(function () use ($user, $profile, $private): void {
            $user->speakerProfile()->updateOrCreate(['user_id' => $user->id], $profile);
            $user->speakerPrivateDetails()->updateOrCreate(['user_id' => $user->id], $private);
        });

        return $user->load(['speakerProfile', 'speakerPrivateDetails']);
    }
}
