<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use PHPeste\Identity\Models\User;

class SessionController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $current = $user->currentAccessToken()->getKey();

        return response()->json(['data' => array_values($user->tokens()->latest('last_used_at')->latest('id')->get()->map(fn (PersonalAccessToken $token): array => [
            'id' => (string) $token->getKey(),
            'name' => $token->name,
            'current' => $token->getKey() === $current,
            'last_used_at' => $token->last_used_at?->clone()->utc()->toIso8601ZuluString(),
            'created_at' => $token->created_at?->clone()->utc()->toIso8601ZuluString(),
        ])->all())]);
    }

    public function destroy(Request $request): Response
    {
        $id = $request->route('session');
        abort_unless(is_string($id) && ctype_digit($id), 404);

        $deleted = $this->user($request)->tokens()->whereKey((int) $id)->delete();
        abort_if($deleted === 0, 404);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
