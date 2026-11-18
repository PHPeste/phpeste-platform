<?php

declare(strict_types=1);

namespace PHPeste\Identity\Contracts;

interface EditionParticipation
{
    public const string TAG = 'identity.edition-participation';

    public function participates(string $editionId, string $userId): bool;
}
