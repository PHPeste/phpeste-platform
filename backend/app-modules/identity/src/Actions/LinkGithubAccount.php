<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use PHPeste\Identity\Models\SocialAccount;
use PHPeste\Identity\Models\User;

class LinkGithubAccount
{
    public function handle(User $user, SocialiteUser $githubUser): void
    {
        $githubId = (string) $githubUser->getId();

        $owner = SocialAccount::query()
            ->where('provider', SocialAccount::GITHUB)
            ->where('provider_user_id', $githubId)
            ->first();

        if ($owner instanceof SocialAccount && $owner->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'github' => 'Esta conta do GitHub já está conectada a outro usuário.',
            ]);
        }

        $current = $user->socialAccounts()->where('provider', SocialAccount::GITHUB)->first();

        if ($current instanceof SocialAccount && $current->provider_user_id !== $githubId) {
            throw ValidationException::withMessages([
                'github' => 'Você já tem outra conta do GitHub conectada. Desconecte ela antes.',
            ]);
        }

        $user->socialAccounts()->updateOrCreate(
            ['provider' => SocialAccount::GITHUB],
            ['provider_user_id' => $githubId, ...AuthenticateWithGithub::profile($githubUser)],
        );
    }
}
