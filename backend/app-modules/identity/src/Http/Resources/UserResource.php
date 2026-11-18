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
            'avatar_url' => $user->photoUrl(),
            'locale' => $user->locale,
            'email_verified_at' => $this->isoDate($user->email_verified_at),
            'has_password' => $user->hasPassword(),
            'providers' => $user->socialAccounts()->pluck('provider')->values()->all(),
            'created_at' => $this->isoDate($user->created_at),
        ];
    }
}
