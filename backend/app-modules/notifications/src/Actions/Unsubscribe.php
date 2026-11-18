<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use PHPeste\Notifications\Models\EmailUnsubscribe;

class Unsubscribe
{
    public function handle(string $editionId, string $email): void
    {
        EmailUnsubscribe::query()->forEdition($editionId)->firstOrCreate([
            'edition_id' => $editionId,
            'email' => mb_strtolower(mb_trim($email)),
        ]);
    }
}
