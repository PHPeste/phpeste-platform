<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Http\Resources\PublicSpeakerProfileResource;
use PHPeste\Identity\Models\SpeakerProfile;

class EloquentSpeakerProfiles implements SpeakerProfiles
{
    public function publicProfile(string $userId): ?array
    {
        $profile = SpeakerProfile::query()->with('user')->where('user_id', $userId)->first();

        return $profile === null ? null : PublicSpeakerProfileResource::shape($profile);
    }

    public function states(array $userIds): array
    {
        $states = array_fill_keys($userIds, null);

        foreach (SpeakerProfile::query()->whereIn('user_id', $userIds)->get() as $profile) {
            $states[$profile->user_id] = $profile->state?->value;
        }

        return $states;
    }

    public function companies(array $userIds): array
    {
        $companies = array_fill_keys($userIds, null);

        foreach (SpeakerProfile::query()->whereIn('user_id', $userIds)->get() as $profile) {
            $company = $profile->company === null ? '' : mb_strtolower(mb_trim($profile->company));
            $companies[$profile->user_id] = $company === '' ? null : $company;
        }

        return $companies;
    }
}
