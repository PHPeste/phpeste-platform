<?php

declare(strict_types=1);

namespace PHPeste\Identity\Contracts;

interface SpeakerProfiles
{
    /**
     * @return array{user_id: string, display_name: string, job_title: string|null, company: string|null, bio: string|null, city: string|null, state: string|null, links: array<string, string|null>, photo_url: string|null}|null
     */
    public function publicProfile(string $userId): ?array;

    /**
     * @param  list<string>  $userIds
     * @return array<string, string|null>
     */
    public function states(array $userIds): array;

    /**
     * @param  list<string>  $userIds
     * @return array<string, string|null>
     */
    public function companies(array $userIds): array;
}
