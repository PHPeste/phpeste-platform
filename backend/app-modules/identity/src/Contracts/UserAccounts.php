<?php

declare(strict_types=1);

namespace PHPeste\Identity\Contracts;

use Laravel\Sanctum\NewAccessToken;
use PHPeste\Identity\Enums\NotificationTopic;

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

    /**
     * @return array{id: string, name: string, email: string, created: bool}
     */
    public function findOrCreateInvited(string $name, string $email): array;

    public function checkPassword(string $userId, string $password): bool;

    /**
     * Se a pessoa quer receber e-mail deste assunto (preferência em /me).
     */
    public function wantsNotification(string $userId, NotificationTopic $topic): bool;

    public function issueToken(string $userId, string $deviceName): NewAccessToken;
}
