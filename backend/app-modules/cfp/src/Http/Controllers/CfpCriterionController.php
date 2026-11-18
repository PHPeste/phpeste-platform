<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\DeleteCriterion;
use PHPeste\Cfp\Actions\SaveCriterion;
use PHPeste\Cfp\Http\Requests\SaveCriterionRequest;
use PHPeste\Cfp\Http\Resources\CfpCriterionResource;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;

class CfpCriterionController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('cfp.view-settings');

        return CfpCriterionResource::collection(CfpCriterion::query()->orderBy('position')->get());
    }

    public function store(SaveCriterionRequest $request, SaveCriterion $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $criterion = $save->handle((string) $this->currentEdition->id(), null, $request->criterion(), $request->boolean('confirm'));

        return new CfpCriterionResource($criterion)->response()->setStatusCode(201);
    }

    public function update(SaveCriterionRequest $request, SaveCriterion $save): CfpCriterionResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new CfpCriterionResource($save->handle((string) $this->currentEdition->id(), $this->criterion($request), $request->criterion(), $request->boolean('confirm')));
    }

    public function destroy(Request $request, DeleteCriterion $delete): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $delete->handle($this->criterion($request), $request->boolean('confirm'));

        return response()->noContent();
    }

    private function criterion(Request $request): CfpCriterion
    {
        $id = $request->route('criterion');

        abort_unless(is_string($id), 404);

        return CfpCriterion::query()->whereKey($id)->firstOrFail();
    }
}
