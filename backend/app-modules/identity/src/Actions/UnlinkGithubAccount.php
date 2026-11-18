<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Identity\Models\SocialAccount;
use PHPeste\Identity\Models\User;

class UnlinkGithubAccount
{
    public function handle(User $user): void
    {
        $github = $user->socialAccounts()->where('provider', SocialAccount::GITHUB)->first();

        if (!$github instanceof SocialAccount) {
            return;
        }

        $otherProviders = $user->socialAccounts()->whereKeyNot($github->id)->exists();

        if (!$user->hasPassword() && !$otherProviders) {
            throw ValidationException::withMessages([
                'github' => 'Defina uma senha antes de desconectar o GitHub, senão você perde o acesso à conta.',
            ]);
        }

        $github->delete();
    }
}
