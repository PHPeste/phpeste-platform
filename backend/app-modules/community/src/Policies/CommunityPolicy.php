<?php

declare(strict_types=1);

namespace PHPeste\Community\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPeste\Community\Models\Community;

class CommunityPolicy
{
    public function view(Authenticatable $user, Community $community): bool
    {
        return $community->isManagedBy($this->id($user));
    }

    public function update(Authenticatable $user, Community $community): bool
    {
        return $community->isManagedBy($this->id($user));
    }

    public function delete(Authenticatable $user, Community $community): bool
    {
        return $community->isManagedBy($this->id($user));
    }

    public function createEdition(Authenticatable $user, Community $community): bool
    {
        return $community->isManagedBy($this->id($user));
    }

    private function id(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
