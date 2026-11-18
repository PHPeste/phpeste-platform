<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Branding\Actions\AddDomain;
use PHPeste\Branding\Actions\RemoveDomain;
use PHPeste\Branding\Actions\SetPrimaryDomain;
use PHPeste\Branding\Actions\VerifyDomain;
use PHPeste\Branding\Http\Requests\AddDomainRequest;
use PHPeste\Branding\Http\Resources\EditionDomainResource;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Community\Enums\EditionPermission;

class EditionDomainController
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return EditionDomainResource::collection(EditionDomain::query()->orderByDesc('is_primary')->oldest()->get());
    }

    public function store(AddDomainRequest $request, AddDomain $addDomain): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new EditionDomainResource($addDomain->handle($request->host()))->response()->setStatusCode(201);
    }

    public function verify(Request $request, VerifyDomain $verifyDomain): EditionDomainResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new EditionDomainResource($verifyDomain->handle($this->domain($request)));
    }

    public function makePrimary(Request $request, SetPrimaryDomain $setPrimary): EditionDomainResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new EditionDomainResource($setPrimary->handle($this->domain($request)));
    }

    public function destroy(Request $request, RemoveDomain $removeDomain): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $removeDomain->handle($this->domain($request));

        return response()->noContent();
    }

    private function domain(Request $request): EditionDomain
    {
        $id = $request->route('domain');

        abort_unless(is_string($id), 404);

        return EditionDomain::query()->whereKey($id)->firstOrFail();
    }
}
