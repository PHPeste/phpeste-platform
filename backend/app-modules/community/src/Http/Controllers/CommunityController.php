<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Actions\CreateCommunity;
use PHPeste\Community\Actions\DeleteCommunity;
use PHPeste\Community\Actions\UpdateCommunity;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Http\Requests\StoreCommunityRequest;
use PHPeste\Community\Http\Requests\UpdateCommunityRequest;
use PHPeste\Community\Http\Resources\CommunityResource;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\EditionMember;

class CommunityController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $this->userId($request);

        $communities = Community::query()
            ->where('created_by', $userId)
            ->orWhereIn('id', EditionMember::query()
                ->join('editions', 'editions.id', '=', 'edition_members.edition_id')
                ->where('edition_members.user_id', $userId)
                ->where('edition_members.role', EditionRole::Organizer)
                ->select('editions.community_id'))
            ->orderBy('name')
            ->get();

        return CommunityResource::collection($communities);
    }

    public function store(StoreCommunityRequest $request, CreateCommunity $createCommunity): JsonResponse
    {
        $community = $createCommunity->handle(
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $this->userId($request),
        );

        return new CommunityResource($community)->response()->setStatusCode(201);
    }

    public function show(Community $community): CommunityResource
    {
        Gate::authorize('view', $community);

        return new CommunityResource($community);
    }

    public function update(UpdateCommunityRequest $request, Community $community, UpdateCommunity $updateCommunity): CommunityResource
    {
        Gate::authorize('update', $community);

        /** @var array{name?: string, slug?: string} $attributes */
        $attributes = $request->validated();

        return new CommunityResource($updateCommunity->handle($community, $attributes));
    }

    public function destroy(Community $community, DeleteCommunity $deleteCommunity): Response
    {
        Gate::authorize('delete', $community);

        $deleteCommunity->handle($community);

        return response()->noContent();
    }

    private function userId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
