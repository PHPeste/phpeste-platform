<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Community\Actions\AcceptInvitation;
use PHPeste\Community\Http\Requests\AcceptInvitationRequest;
use PHPeste\Identity\Contracts\UserAccounts;

class InvitationController
{
    public function __construct(private readonly UserAccounts $accounts) {}

    public function show(string $token): JsonResponse
    {
        $invitation = AcceptInvitation::findUsable($token);
        $inviter = $invitation->invited_by === null ? null : ($this->accounts->profiles([$invitation->invited_by])[$invitation->invited_by] ?? null);

        return response()->json([
            'data' => [
                'edition' => ['id' => $invitation->edition_id, 'name' => $invitation->edition->name],
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'invited_by' => $inviter['name'] ?? null,
                'account_exists' => $this->accounts->findByEmail($invitation->email) !== null,
                'expires_at' => $invitation->expires_at->clone()->utc()->toIso8601ZuluString(),
            ],
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token, AcceptInvitation $accept): JsonResponse
    {
        $invitation = AcceptInvitation::findUsable($token);
        $authenticated = $request->user('sanctum')?->getAuthIdentifier();
        $authenticatedId = is_string($authenticated) ? $authenticated : null;

        if ($authenticatedId === null && $this->accounts->findByEmail($invitation->email) === null) {
            $request->validate(AcceptInvitationRequest::newAccountRules());
        }

        $account = $accept->handle(
            $token,
            $authenticatedId,
            $request->filled('name') ? $request->string('name')->toString() : null,
            $request->filled('password') ? $request->string('password')->toString() : null,
        );

        $payload = ['user' => $account, 'edition_id' => $invitation->edition_id];

        if ($authenticatedId === null) {
            $deviceName = $request->string('device_name')->toString() ?: ($request->userAgent() ?? 'desconhecido');
            $payload['token'] = $this->accounts->issueToken($account['id'], $deviceName)->plainTextToken;
        }

        return response()->json($payload);
    }
}
