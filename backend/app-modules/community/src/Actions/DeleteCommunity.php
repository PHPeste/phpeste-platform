<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Community\Models\Community;

class DeleteCommunity
{
    public function handle(Community $community): void
    {
        if ($community->editions()->exists()) {
            throw ValidationException::withMessages([
                'community' => 'Só é possível remover uma comunidade sem edições.',
            ]);
        }

        $community->delete();
    }
}
