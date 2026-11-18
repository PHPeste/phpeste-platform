<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Identity\Models\SpeakerProfile;

class PublicSpeakerProfileResource extends ApiResource
{
    /**
     * @return array{user_id: string, display_name: string, job_title: string|null, company: string|null, bio: string|null, city: string|null, state: string|null, links: array<string, string|null>, photo_url: string|null}
     */
    public static function shape(SpeakerProfile $profile): array
    {
        return [
            'user_id' => $profile->user_id,
            'display_name' => $profile->display_name,
            'job_title' => $profile->job_title,
            'company' => $profile->company,
            'bio' => $profile->bio,
            'city' => $profile->city,
            'state' => $profile->state?->value,
            'links' => array_merge(array_fill_keys(SpeakerProfile::LINKS, null), $profile->links),
            'photo_url' => $profile->user->photoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource instanceof SpeakerProfile ? self::shape($this->resource) : [];
    }
}
