<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use PHPeste\Identity\Actions\AuthenticateWithGithub;
use PHPeste\Identity\Actions\GithubState;
use PHPeste\Identity\Actions\LinkGithubAccount;
use PHPeste\Identity\Actions\UnlinkGithubAccount;
use PHPeste\Identity\Http\Requests\GithubCallbackRequest;
use PHPeste\Identity\Http\Resources\UserResource;
use PHPeste\Identity\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Throwable;

class GithubController
{
    public function redirect(): JsonResponse
    {
        return response()->json(['url' => $this->authorizationUrl(GithubState::issue(GithubState::LOGIN))]);
    }

    public function linkRedirect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['url' => $this->authorizationUrl(GithubState::issue(GithubState::LINK, $user->id))]);
    }

    public function callback(GithubCallbackRequest $request, AuthenticateWithGithub $authenticate, LinkGithubAccount $link): JsonResponse
    {
        $state = GithubState::decode($request->string('state')->toString());

        if ($state['intent'] === GithubState::LINK) {
            $user = $request->user('sanctum');

            throw_if(!$user instanceof User || $user->id !== $state['user'], AccessDeniedHttpException::class, 'Entre na conta que pediu a conexão com o GitHub.');

            $link->handle($user, $this->githubUser());

            return response()->json(['user' => new UserResource($user->refresh())->resolve($request)]);
        }

        $user = $authenticate->handle($this->githubUser());
        $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?? 'desconhecido');

        return response()->json([
            'user' => new UserResource($user)->resolve($request),
            'token' => $user->createToken($deviceName)->plainTextToken,
        ]);
    }

    public function unlink(Request $request, UnlinkGithubAccount $unlink): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $unlink->handle($user);

        return response()->json(['user' => new UserResource($user->refresh())->resolve($request)]);
    }

    private function authorizationUrl(string $state): string
    {
        $provider = $this->provider();

        if ($provider instanceof AbstractProvider) {
            $provider->with(['state' => $state]);
        }

        return $provider->redirect()->getTargetUrl();
    }

    private function githubUser(): SocialiteUser
    {
        try {
            return $this->provider()->user();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'code' => 'Não foi possível confirmar o acesso no GitHub. Tente entrar de novo.',
            ]);
        }
    }

    private function provider(): Provider
    {
        /** @var Provider $driver */
        $driver = Socialite::driver('github');

        return $driver instanceof AbstractProvider ? $driver->stateless()->scopes(['user:email']) : $driver;
    }
}
