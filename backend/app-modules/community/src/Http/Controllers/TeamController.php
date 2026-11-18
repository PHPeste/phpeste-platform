<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Actions\ChangeMemberRole;
use PHPeste\Community\Actions\InviteTeamMember;
use PHPeste\Community\Actions\RemoveMember;
use PHPeste\Community\Actions\ResendInvitation;
use PHPeste\Community\Actions\RevokeInvitation;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Http\Requests\ChangeMemberRoleRequest;
use PHPeste\Community\Http\Requests\InviteTeamMemberRequest;
use PHPeste\Community\Http\Resources\TeamInvitationResource;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionInvitation;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Contracts\UserAccounts;

class TeamController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly UserAccounts $accounts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        $edition = $this->edition();
        $members = $edition->members()->oldest()->get();
        $profiles = $this->accounts->profiles(array_values($members->map(fn (EditionMember $member): string => $member->user_id)->all()));
        $invitations = EditionInvitation::query()->where('edition_id', $edition->id)->pending()->oldest()->get();

        return response()->json([
            'data' => [
                'members' => $members->map(fn (EditionMember $member): array => $this->member($member, $profiles[$member->user_id] ?? null))->values()->all(),
                'invitations' => TeamInvitationResource::collection($invitations)->resolve($request),
                'summary' => [
                    'people' => $members->count(),
                    'pending_invitations' => $invitations->count(),
                    'by_role' => array_combine(
                        array_column(EditionRole::cases(), 'value'),
                        array_map(fn (EditionRole $role): int => $members->where('role', $role)->count(), EditionRole::cases()),
                    ),
                ],
            ],
        ]);
    }

    public function invite(InviteTeamMemberRequest $request, InviteTeamMember $invite): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        $invitation = $invite->handle(
            $this->edition(),
            $request->string('email')->toString(),
            EditionRole::from($request->string('role')->toString()),
            $request->filled('name') ? $request->string('name')->toString() : null,
            $this->actorId($request),
        );

        return new TeamInvitationResource($invitation)->response()->setStatusCode(201);
    }

    public function resendInvitation(Request $request, ResendInvitation $resend): TeamInvitationResource
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        return new TeamInvitationResource($resend->handle($this->invitation($request)));
    }

    public function revokeInvitation(Request $request, RevokeInvitation $revoke): Response
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        $revoke->handle($this->invitation($request), $this->actorId($request));

        return response()->noContent();
    }

    public function changeRole(ChangeMemberRoleRequest $request, ChangeMemberRole $changeRole): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        $member = $changeRole->handle(
            $this->memberFromRoute($request),
            EditionRole::from($request->string('role')->toString()),
            $this->actorId($request),
        );

        return response()->json(['data' => $this->member($member, $this->accounts->profiles([$member->user_id])[$member->user_id] ?? null)]);
    }

    public function removeMember(Request $request, RemoveMember $removeMember): Response
    {
        Gate::authorize(EditionPermission::ManageTeam->value);

        $removeMember->handle($this->memberFromRoute($request), $this->actorId($request));

        return response()->noContent();
    }

    /**
     * @param  array{id: string, name: string, email: string, last_seen_at: string|null}|null  $profile
     * @return array<string, mixed>
     */
    private function member(EditionMember $member, ?array $profile): array
    {
        return [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'name' => $profile['name'] ?? null,
            'email' => $profile['email'] ?? null,
            'role' => $member->role->value,
            'role_label' => $member->role->label(),
            'access' => $member->role->access(),
            'last_seen_at' => $profile === null || $profile['last_seen_at'] === null
                ? null
                : Date::parse($profile['last_seen_at'])->utc()->toIso8601ZuluString(),
            'joined_at' => $member->created_at->clone()->utc()->toIso8601ZuluString(),
        ];
    }

    private function edition(): Edition
    {
        return Edition::query()->findOrFail($this->currentEdition->id());
    }

    private function memberFromRoute(Request $request): EditionMember
    {
        $id = $request->route('member');

        abort_unless(is_string($id), 404);

        return EditionMember::query()->where('edition_id', $this->currentEdition->id())->whereKey($id)->firstOrFail();
    }

    private function invitation(Request $request): EditionInvitation
    {
        $id = $request->route('invitation');

        abort_unless(is_string($id), 404);

        return EditionInvitation::query()->where('edition_id', $this->currentEdition->id())->whereKey($id)->firstOrFail();
    }

    private function actorId(Request $request): string
    {
        return (string) $request->user()?->getAuthIdentifier();
    }
}
