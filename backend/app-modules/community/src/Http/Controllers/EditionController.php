<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Actions\CreateEdition;
use PHPeste\Community\Actions\DeleteEdition;
use PHPeste\Community\Actions\UpdateEdition;
use PHPeste\Community\Http\Requests\StoreEditionRequest;
use PHPeste\Community\Http\Requests\UpdateEditionRequest;
use PHPeste\Community\Http\Resources\EditionResource;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;

class EditionController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $this->userId($request);

        $editions = Edition::query()
            ->whereHas('members', fn (Builder $query) => $query->where('user_id', $userId))
            ->with(['community', 'members' => $this->membershipOf($userId)])
            ->latest('starts_on')
            ->orderBy('name')
            ->get();

        return EditionResource::collection($editions);
    }

    public function indexForCommunity(Request $request, Community $community): AnonymousResourceCollection
    {
        Gate::authorize('view', $community);

        $editions = $community->editions()
            ->with(['members' => $this->membershipOf($this->userId($request))])
            ->latest('starts_on')
            ->orderBy('name')
            ->get();

        return EditionResource::collection($editions);
    }

    public function store(StoreEditionRequest $request, Community $community, CreateEdition $createEdition): JsonResponse
    {
        Gate::authorize('createEdition', $community);

        /** @var array{name: string, slug: string, starts_on?: string|null, ends_on?: string|null, city?: string|null, state?: string|null, timezone?: string} $attributes */
        $attributes = $request->validated();

        $edition = $createEdition->handle($community, $attributes, $this->userId($request));

        return $this->resource($edition, $request)->response()->setStatusCode(201);
    }

    public function show(Request $request, Edition $edition): EditionResource
    {
        Gate::authorize('view', $edition);

        return $this->resource($edition, $request);
    }

    public function update(UpdateEditionRequest $request, Edition $edition, UpdateEdition $updateEdition): EditionResource
    {
        Gate::authorize('update', $edition);

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        return $this->resource($updateEdition->handle($edition, $attributes, (string) $request->user()?->getAuthIdentifier()), $request);
    }

    public function destroy(Edition $edition, DeleteEdition $deleteEdition): Response
    {
        Gate::authorize('delete', $edition);

        $deleteEdition->handle($edition);

        return response()->noContent();
    }

    private function resource(Edition $edition, Request $request): EditionResource
    {
        return new EditionResource($edition->load(['members' => $this->membershipOf($this->userId($request))]));
    }

    /**
     * @return Closure(HasMany<EditionMember, Edition>): void
     */
    private function membershipOf(string $userId): Closure
    {
        return function (HasMany $query) use ($userId): void {
            $query->where('user_id', $userId);
        };
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
