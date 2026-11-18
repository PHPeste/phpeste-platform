<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\Password;

class SendPasswordResetLink
{
    public function handle(string $email): void
    {
        Password::broker()->sendResetLink(['email' => mb_strtolower(mb_trim($email))]);
    }
}
