<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use PHPeste\Identity\Actions\AuthenticateUser;
use PHPeste\Identity\Actions\RegisterUser;
use PHPeste\Identity\Http\Requests\LoginRequest;
use PHPeste\Identity\Http\Requests\RegisterRequest;
use PHPeste\Identity\Http\Resources\UserResource;
use PHPeste\Identity\Models\User;

class AuthController
{
    public function register(RegisterRequest $request, RegisterUser $registerUser): JsonResponse
    {
        $user = $registerUser->handle(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return new UserResource($user)->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, AuthenticateUser $authenticateUser): JsonResponse
    {
        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?? 'desconhecido');

        $token = $authenticateUser->handle(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $deviceName,
        );

        /** @var User $user */
        $user = $token->accessToken->tokenable;

        return response()->json([
            'user' => new UserResource($user)->resolve($request),
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var PersonalAccessToken $token */
        $token = $user->currentAccessToken();
        $token->delete();

        return response()->json(['loggedOut' => true]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->tokens()->delete();

        return response()->json(['loggedOut' => true]);
    }

    /**
     * A assinatura da URL (id + hash + expiração) já foi conferida pelo
     * middleware `signed` antes de chegar aqui.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = User::findOrFail($request->route('id'));

        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return response()->json(['verified' => true]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['sent' => true]);
    }
}
