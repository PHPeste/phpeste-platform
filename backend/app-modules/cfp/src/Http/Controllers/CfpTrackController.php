<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\DeleteTrack;
use PHPeste\Cfp\Actions\SaveTrack;
use PHPeste\Cfp\Http\Requests\SaveTrackRequest;
use PHPeste\Cfp\Http\Resources\CfpTrackResource;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Enums\EditionPermission;

class CfpTrackController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('cfp.view-settings');

        return CfpTrackResource::collection(CfpTrack::query()->orderBy('position')->orderBy('name')->get());
    }

    public function store(SaveTrackRequest $request, SaveTrack $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array{name?: string, slots?: int, position?: int} $attributes */
        $attributes = $request->validated();

        return new CfpTrackResource($save->handle(null, $attributes))->response()->setStatusCode(201);
    }

    public function update(SaveTrackRequest $request, SaveTrack $save): CfpTrackResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array{name?: string, slots?: int, position?: int} $attributes */
        $attributes = $request->validated();

        return new CfpTrackResource($save->handle($this->track($request), $attributes));
    }

    public function destroy(Request $request, DeleteTrack $delete): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $delete->handle($this->track($request));

        return response()->noContent();
    }

    private function track(Request $request): CfpTrack
    {
        $id = $request->route('track');

        abort_unless(is_string($id), 404);

        return CfpTrack::query()->whereKey($id)->firstOrFail();
    }
}
