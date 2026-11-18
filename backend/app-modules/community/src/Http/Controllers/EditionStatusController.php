<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Actions\TransitionEditionStatus;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Http\Requests\ChangeEditionStatusRequest;
use PHPeste\Community\Http\Resources\EditionResource;
use PHPeste\Community\Models\Edition;

class EditionStatusController
{
    public function __invoke(ChangeEditionStatusRequest $request, Edition $edition, TransitionEditionStatus $transition): EditionResource
    {
        Gate::authorize('changeStatus', $edition);

        $next = $request->enum('status', EditionStatus::class);

        abort_if($next === null, 422);

        return new EditionResource($transition->handle($edition, $next, (string) $request->user()?->getAuthIdentifier()));
    }
}
