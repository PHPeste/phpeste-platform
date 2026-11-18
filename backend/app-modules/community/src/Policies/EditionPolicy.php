<?php

declare(strict_types=1);

namespace PHPeste\Community\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;

class EditionPolicy
{
    public function view(Authenticatable $user, Edition $edition): bool
    {
        return $edition->roleOf($this->id($user)) instanceof EditionRole;
    }

    public function update(Authenticatable $user, Edition $edition): bool
    {
        return $edition->roleOf($this->id($user)) === EditionRole::Organizer;
    }

    public function delete(Authenticatable $user, Edition $edition): bool
    {
        return $edition->roleOf($this->id($user)) === EditionRole::Organizer;
    }

    public function changeStatus(Authenticatable $user, Edition $edition): bool
    {
        return $edition->roleOf($this->id($user)) === EditionRole::Organizer;
    }

    private function id(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
