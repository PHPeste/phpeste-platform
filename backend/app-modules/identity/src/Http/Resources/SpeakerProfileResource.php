<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Identity\Models\User;

class SpeakerProfileResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        if (!$user instanceof User) {
            return [];
        }

        $profile = $user->speakerProfile;
        $private = $user->speakerPrivateDetails;

        return [
            'public' => $profile === null ? null : PublicSpeakerProfileResource::shape($profile),
            'private' => [
                'accessibility_needs' => $private?->accessibility_needs,
                'dietary_restrictions' => $private?->dietary_restrictions,
            ],
            'email' => $user->email,
            'updated_at' => $this->isoDate($profile?->updated_at),
        ];
    }
}
