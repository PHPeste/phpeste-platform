<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use PHPeste\Identity\Models\User;

class UpdateAccount
{
    /**
     * @param  array{name?: string, locale?: string, notifications?: array<string, bool>}  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        if (isset($attributes['notifications'])) {
            $attributes['notification_preferences'] = [...($user->notification_preferences ?? []), ...array_map(fn (mixed $value): bool => (bool) $value, $attributes['notifications'])];
            unset($attributes['notifications']);
        }

        $user->update($attributes);

        return $user;
    }
}
