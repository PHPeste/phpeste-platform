<?php

declare(strict_types=1);

namespace PHPeste\Identity\Contracts;

use Laravel\Sanctum\NewAccessToken;

interface UserAccounts
{
    /**
     * @return array{id: string, name: string, email: string}|null
     */
    public function findByEmail(string $email): ?array;

    /**
     * @param  list<string>  $ids
     * @return array<string, array{id: string, name: string, email: string, last_seen_at: string|null}>
     */
    public function profiles(array $ids): array;

    /**
     * @return array{id: string, name: string, email: string}
     */
    public function registerVerified(string $name, string $email, string $password): array;

    public function checkPassword(string $userId, string $password): bool;

    public function issueToken(string $userId, string $deviceName): NewAccessToken;
}
