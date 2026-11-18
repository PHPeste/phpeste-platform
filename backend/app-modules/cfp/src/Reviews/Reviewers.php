<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Reviews;

use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Identity\Contracts\UserAccounts;

class Reviewers
{
    public function __construct(
        private readonly EditionMemberships $memberships,
        private readonly UserAccounts $accounts,
    ) {}

    public function canReview(string $editionId, string $userId): bool
    {
        return $this->memberships->can($editionId, $userId, EditionPermission::ReviewProposals);
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, string>
     */
    public function names(array $userIds): array
    {
        return array_map(fn (array $profile): string => $profile['name'], $this->accounts->profiles($userIds));
    }
}
