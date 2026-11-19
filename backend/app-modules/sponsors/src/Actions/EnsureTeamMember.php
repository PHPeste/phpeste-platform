<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionRole;

/**
 * Responsável por acordo ou entrega tem que ser da equipe da edição.
 */
class EnsureTeamMember
{
    public function __construct(private readonly EditionMemberships $memberships) {}

    public function handle(string $editionId, ?string $userId, string $field): void
    {
        if ($userId !== null && !$this->memberships->roleIn($editionId, $userId) instanceof EditionRole) {
            throw ValidationException::withMessages([$field => 'O responsável precisa ser da equipe desta edição.']);
        }
    }
}
