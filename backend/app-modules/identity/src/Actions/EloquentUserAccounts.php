<?php

declare(strict_types=1);

namespace PHPeste\Identity\Actions;

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use PHPeste\Identity\Contracts\UserAccounts;
use PHPeste\Identity\Models\User;

class EloquentUserAccounts implements UserAccounts
{
    public function findByEmail(string $email): ?array
    {
        $user = User::query()->whereRaw('lower(email) = ?', [mb_strtolower(mb_trim($email))])->first();

        return $user === null ? null : ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    public function profiles(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $lastSeen = PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', $ids)
            ->whereNotNull('last_used_at')
            ->groupBy('tokenable_id')
            ->selectRaw('tokenable_id, max(last_used_at) as last_seen_at')
            ->pluck('last_seen_at', 'tokenable_id');

        $profiles = [];

        foreach (User::query()->whereIn('id', $ids)->get() as $user) {
            $seen = $lastSeen->get($user->id);

            $profiles[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'last_seen_at' => is_string($seen) ? $seen : null,
            ];
        }

        return $profiles;
    }

    public function findOrCreateInvited(string $name, string $email): array
    {
        $existing = $this->findByEmail($email);

        if ($existing !== null) {
            return [...$existing, 'created' => false];
        }

        $user = User::create([
            'name' => $name,
            'email' => mb_strtolower(mb_trim($email)),
            'password' => null,
            'locale' => app()->getLocale(),
        ]);

        $user->markEmailAsVerified();

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'created' => true];
    }

    public function registerVerified(string $name, string $email, string $password): array
    {
        $user = User::create([
            'name' => $name,
            'email' => mb_strtolower(mb_trim($email)),
            'password' => $password,
            'locale' => app()->getLocale(),
        ]);

        $user->markEmailAsVerified();

        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
    }

    public function checkPassword(string $userId, string $password): bool
    {
        $hash = User::query()->find($userId)?->password;

        return $hash !== null && Hash::check($password, $hash);
    }

    public function issueToken(string $userId, string $deviceName): NewAccessToken
    {
        return User::query()->findOrFail($userId)->createToken($deviceName);
    }
}
