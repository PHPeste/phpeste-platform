<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use PHPeste\Identity\Models\User;

class UpdateAccount
{
    /**
     * @param  array{name?: string, locale?: string}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user;
    }
}
