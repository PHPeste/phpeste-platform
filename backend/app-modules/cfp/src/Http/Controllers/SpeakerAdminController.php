<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Cfp\Actions\InviteSpeaker;
use PHPeste\Cfp\Actions\MarkSpeakerAttendance;
use PHPeste\Cfp\Actions\RemindSpeakers;
use PHPeste\Cfp\Confirmations\LogisticsPresenter;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Http\Requests\InviteSpeakerRequest;
use PHPeste\Cfp\Http\Requests\RemindSpeakersRequest;
use PHPeste\Cfp\Http\Requests\SpeakerAttendanceRequest;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerLogistics;
use PHPeste\Cfp\Speakers\SpeakerBoard;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Contracts\UserAccounts;

class SpeakerAdminController
{
    public function __construct(
        private readonly SpeakerBoard $board,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $board = $this->board->build($this->editionId());

        return response()->json(['summary' => $board['summary'], 'coverage' => $board['coverage'], 'data' => $board['rows']]);
    }

    public function show(Request $request, UserAccounts $accounts, SpeakerProfiles $profiles, LogisticsPresenter $logistics): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $userId = $this->userId($request);
        $proposals = $this->board->proposals($this->editionId(), $userId);
        abort_if($proposals->isEmpty(), 404);

        $account = $accounts->profiles([$userId])[$userId] ?? null;
        $canViewPrivate = Gate::allows(EditionPermission::ViewSpeakerPrivateData->value);
        $speaks = $proposals->contains(fn (Proposal $proposal): bool => $proposal->decision === ProposalDecision::Accepted);
        $record = $speaks ? SpeakerLogistics::query()->firstOrCreate(['edition_id' => $this->editionId(), 'user_id' => $userId]) : null;

        return response()->json(['data' => [
            'user_id' => $userId,
            'name' => $account['name'] ?? null,
            'email' => $account['email'] ?? null,
            'profile' => $profiles->publicProfile($userId),
            'can_view_private_data' => $canViewPrivate,
            'proposals' => array_values($proposals->map(fn (Proposal $proposal): array => [
                'id' => $proposal->id,
                'number' => $proposal->number,
                'title' => $proposal->title,
                'track' => $proposal->track?->name,
                'origin' => $proposal->origin->value,
                'origin_label' => $proposal->origin->label(),
                'format_label' => $proposal->format?->label(),
                'duration_minutes' => $proposal->format?->minutes(),
                'decision' => $proposal->decision?->value,
                'decision_label' => $proposal->decision?->label(),
                'status' => SpeakerBoard::status($proposal),
                'status_label' => SpeakerBoard::statusLabel($proposal),
                'deadline_at' => $proposal->confirmation?->deadline_at->clone()->utc()->toIso8601ZuluString(),
                'responded_at' => $proposal->confirmation?->responded_at?->clone()->utc()->toIso8601ZuluString(),
                'reminded_at' => $proposal->confirmation?->reminded_at?->clone()->utc()->toIso8601ZuluString(),
                'attended_at' => $proposal->confirmation?->attended_at?->clone()->utc()->toIso8601ZuluString(),
                'reason' => $proposal->confirmation?->reason,
            ])->all()),
            'logistics' => $record === null ? null : $logistics->present($record, true, $canViewPrivate),
        ]]);
    }

    public function invite(InviteSpeakerRequest $request, InviteSpeaker $invite): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $proposal = $invite->handle($this->editionId(), $request->invitation(), (string) $request->user()?->getAuthIdentifier());

        return response()->json(['data' => [
            'proposal_id' => $proposal->id,
            'number' => $proposal->number,
            'user_id' => $proposal->speakers->first()?->user_id,
            'deadline_at' => $proposal->confirmation?->deadline_at->clone()->utc()->toIso8601ZuluString(),
        ]], 201);
    }

    public function remind(RemindSpeakersRequest $request, RemindSpeakers $remind): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => $remind->handle($request->filled('proposal_id') ? $request->string('proposal_id')->toString() : null)]);
    }

    public function attendance(SpeakerAttendanceRequest $request, MarkSpeakerAttendance $mark): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => ['updated' => $mark->handle($this->userId($request), $request->boolean('present'))]]);
    }

    private function editionId(): string
    {
        return (string) $this->currentEdition->id();
    }

    private function userId(Request $request): string
    {
        $id = $request->route('user');
        abort_unless(is_string($id), 404);

        return $id;
    }
}
