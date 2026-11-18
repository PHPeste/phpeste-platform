<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Identity\Http\Resources\UserResource;
use PHPeste\Identity\Models\User;

class MeController
{
    public function __invoke(Request $request, EditionMemberships $memberships): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user)->resolve($request),
            'roles' => $memberships->rolesOf($user->id),
        ]);
    }
}
