<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use PHPeste\Identity\Models\User;

class AuthenticateUser
{
    public function handle(string $email, string $password, string $deviceName): NewAccessToken
    {
        $user = User::where('email', $email)->first();

        throw_if(!$user || !Hash::check($password, $user->password), AuthenticationException::class, 'Credenciais inválidas.');

        return $user->createToken($deviceName);
    }
}
