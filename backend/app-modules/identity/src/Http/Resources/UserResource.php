<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Identity\Models\User;

class UserResource extends ApiResource
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

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->photo_path ? asset('storage/'.$user->photo_path) : null,
            'locale' => $user->locale,
            'email_verified_at' => $this->isoDate($user->email_verified_at),
            'created_at' => $this->isoDate($user->created_at),
        ];
    }
}
