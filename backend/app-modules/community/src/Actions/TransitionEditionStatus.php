<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Contracts\EditionTransitionGuard;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Events\EditionStatusChanged;
use PHPeste\Community\Http\Middleware\AllowEditionSiteOrigins;
use PHPeste\Community\Models\Edition;

class TransitionEditionStatus
{
    public function handle(Edition $edition, EditionStatus $next, ?string $actorId = null): Edition
    {
        $current = $edition->status;

        if (!$current->canTransitionTo($next)) {
            throw ValidationException::withMessages([
                'status' => sprintf('Não é possível passar a edição de "%s" para "%s".', $current->label(), $next->label()),
            ]);
        }

        $blockers = [];

        foreach (app()->tagged(EditionTransitionGuard::TAG) as $guard) {
            if ($guard instanceof EditionTransitionGuard) {
                $blockers = [...$blockers, ...$guard->blockers($edition->id, $next)];
            }
        }

        if ($blockers !== []) {
            throw ValidationException::withMessages(['status' => $blockers]);
        }

        DB::transaction(function () use ($edition, $current, $next, $actorId): void {
            $edition->update(['status' => $next]);

            event(new EditionStatusChanged($edition->id, $current, $next, $actorId));
        });

        AllowEditionSiteOrigins::forget();

        return $edition;
    }
}
