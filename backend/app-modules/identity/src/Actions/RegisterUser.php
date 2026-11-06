<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Auth\Events\Registered;
use PHPeste\Identity\Models\User;

class RegisterUser
{
    public function handle(string $name, string $email, string $password): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'locale' => app()->getLocale(),
        ]);

        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        return $user;
    }
}
