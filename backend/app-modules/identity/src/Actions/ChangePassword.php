<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPeste\Identity\Models\User;

class ChangePassword
{
    public function handle(User $user, ?string $currentPassword, string $newPassword, string|int|null $keepTokenId): void
    {
        $hash = $user->password;

        if ($hash !== null && ($currentPassword === null || !Hash::check($currentPassword, $hash))) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual não confere.',
            ]);
        }

        $user->update(['password' => $newPassword]);

        $user->tokens()
            ->when($keepTokenId !== null, fn ($query) => $query->whereKeyNot($keepTokenId))
            ->delete();
    }
}
