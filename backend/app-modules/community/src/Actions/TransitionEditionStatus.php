<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Events\EditionStatusChanged;
use PHPeste\Community\Models\Edition;

class TransitionEditionStatus
{
    public function handle(Edition $edition, EditionStatus $next): Edition
    {
        $current = $edition->status;

        if (!$current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Não é possível passar a edição de "%s" para "%s".', $current->label(), $next->label()),
            ]);
        }

        DB::transaction(function () use ($edition, $current, $next): void {
            $edition->update(['status' => $next]);

            event(new EditionStatusChanged($edition->id, $current, $next));
        });

        return $edition;
    }
}
