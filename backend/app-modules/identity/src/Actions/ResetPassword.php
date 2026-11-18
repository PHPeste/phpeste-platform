<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Identity\Models\User;

class ResetPassword
{
    public function handle(string $email, string $token, string $password): void
    {
        $status = Password::broker()->reset(
            ['email' => mb_strtolower(mb_trim($email)), 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                if (!$user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }

                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => 'Este link de redefinição é inválido ou expirou. Peça um novo.',
            ]);
        }
    }
}
