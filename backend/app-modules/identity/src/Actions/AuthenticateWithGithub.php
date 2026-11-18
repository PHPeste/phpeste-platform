<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use PHPeste\Identity\Models\SocialAccount;
use PHPeste\Identity\Models\User;

class AuthenticateWithGithub
{
    /**
     * @return array{provider_email: string|null, nickname: string|null, avatar_url: string|null}
     */
    public static function profile(SocialiteUser $githubUser): array
    {
        return [
            'provider_email' => $githubUser->getEmail(),
            'nickname' => $githubUser->getNickname(),
            'avatar_url' => $githubUser->getAvatar(),
        ];
    }

    public function handle(SocialiteUser $githubUser): User
    {
        $linked = SocialAccount::query()
            ->where('provider', SocialAccount::GITHUB)
            ->where('provider_user_id', (string) $githubUser->getId())
            ->first();

        if ($linked instanceof SocialAccount) {
            $linked->update(self::profile($githubUser));

            return $linked->user;
        }

        $email = $githubUser->getEmail();

        if ($email === null || $email === '') {
            throw ValidationException::withMessages([
                'github' => 'Sua conta do GitHub não tem um e-mail principal verificado. Verifique o e-mail no GitHub ou entre com e-mail e senha.',
            ]);
        }

        return DB::transaction(function () use ($githubUser, $email): User {
            $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first()
                ?? User::create([
                    'name' => $githubUser->getName() ?: ($githubUser->getNickname() ?: $email),
                    'email' => mb_strtolower($email),
                    'password' => null,
                    'locale' => app()->getLocale(),
                ]);

            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
            }

            $user->socialAccounts()->create([
                'provider' => SocialAccount::GITHUB,
                'provider_user_id' => (string) $githubUser->getId(),
                ...self::profile($githubUser),
            ]);

            return $user;
        });
    }
}
