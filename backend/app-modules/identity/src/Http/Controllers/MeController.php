<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Identity\Http\Resources\UserResource;
use PHPeste\Identity\Models\User;

class MeController
{
    /**
     * `roles` fica vazio até o módulo Community (#14) existir — é ele quem
     * sabe em quais edições a pessoa tem papel.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => new UserResource($user)->resolve($request),
            'roles' => [],
        ]);
    }
}
