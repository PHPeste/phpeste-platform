<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Actions\ApplyDecisions;
use PHPeste\Cfp\Actions\ExtendConfirmationDeadline;
use PHPeste\Cfp\Confirmations\LogisticsPresenter;
use PHPeste\Cfp\Confirmations\WaitlistPromotions;
use PHPeste\Cfp\Enums\ConfirmationStatus;
use PHPeste\Cfp\Enums\ProposalDecision;
use PHPeste\Cfp\Http\Requests\ExtendDeadlineRequest;
use PHPeste\Cfp\Http\Requests\OrganizationLogisticsRequest;
use PHPeste\Cfp\Http\Requests\PromoteRequest;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Cfp\Models\SpeakerLogistics;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Contracts\UserAccounts;

class ConfirmationAdminController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly UserAccounts $accounts,
        private readonly LogisticsPresenter $logistics,
    ) {}

    public function index(): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $confirmations = SpeakerConfirmation::query()->with(['proposal.speakers', 'proposal.track'])->get()->sortBy(fn (SpeakerConfirmation $confirmation): int => $confirmation->proposal->number);
        $speakerIds = array_values(array_unique($confirmations->flatMap(fn (SpeakerConfirmation $confirmation) => $confirmation->proposal->speakers->pluck('user_id'))->map(fn (mixed $id): string => (string) $id)->all()));
        $profiles = $this->accounts->profiles($speakerIds);
        $summary = array_fill_keys(array_column(ConfirmationStatus::cases(), 'value'), 0);

        foreach ($confirmations as $confirmation) {
            $summary[$confirmation->status->value]++;
        }

        return response()->json([
            'summary' => $summary,
            'data' => array_values($confirmations->map(fn (SpeakerConfirmation $confirmation): array => [
                'proposal_id' => $confirmation->proposal_id,
                'number' => $confirmation->proposal->number,
                'title' => $confirmation->proposal->title,
                'track' => $confirmation->proposal->track?->name,
                'status' => $confirmation->status->value,
                'status_label' => $confirmation->status->label(),
                'deadline_at' => $confirmation->deadline_at->clone()->utc()->toIso8601ZuluString(),
                'responded_at' => $confirmation->responded_at?->clone()->utc()->toIso8601ZuluString(),
                'reason' => $confirmation->reason,
                'speakers' => array_values(array_map(fn (string $id): array => ['user_id' => $id, 'name' => $profiles[$id]['name'] ?? null], $confirmation->proposal->speakers->pluck('user_id')->map(fn (mixed $id): string => (string) $id)->all())),
            ])->all()),
        ]);
    }

    public function extend(ExtendDeadlineRequest $request, ExtendConfirmationDeadline $extend): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        $confirmation = $extend->handle(
            SpeakerConfirmation::query()->where('proposal_id', $id)->firstOrFail(),
            Date::parse($request->string('deadline_at')->toString()),
            (string) $request->user()?->getAuthIdentifier(),
        );

        return response()->json(['data' => ['status' => $confirmation->status->value, 'deadline_at' => $confirmation->deadline_at->clone()->utc()->toIso8601ZuluString()]]);
    }

    public function promotions(WaitlistPromotions $promotions): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => array_map(fn (array $group): array => [
            'track' => $group['track'],
            'occupied' => $group['occupied'],
            'open_slots' => $group['open_slots'],
            'candidates' => array_map(fn (array $candidate): array => [
                'proposal_id' => $candidate['proposal']->id,
                'number' => $candidate['proposal']->number,
                'title' => $candidate['proposal']->title,
                'final_score' => $candidate['final_score'],
            ], $group['candidates']),
        ], $promotions->suggestions((string) $this->currentEdition->id()))]);
    }

    public function promote(PromoteRequest $request, WaitlistPromotions $promotions, ApplyDecisions $apply): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $editionId = (string) $this->currentEdition->id();
        $proposalId = $request->string('proposal_id')->toString();
        $candidates = collect($promotions->suggestions($editionId))->flatMap(fn (array $group): array => array_map(fn (array $candidate): string => $candidate['proposal']->id, $group['candidates']));

        if ($candidates->doesntContain($proposalId)) {
            throw ValidationException::withMessages(['proposal_id' => 'Esta proposta não é a próxima da lista de espera para uma vaga aberta.']);
        }

        $apply->handle($editionId, [$proposalId => ProposalDecision::Accepted], (string) $request->user()?->getAuthIdentifier());

        $proposal = Proposal::query()->with('confirmation')->findOrFail($proposalId);

        return response()->json(['data' => [
            'proposal_id' => $proposal->id,
            'decision' => $proposal->decision?->value,
            'confirmation_status' => $proposal->confirmation?->status->value,
        ]]);
    }

    public function showLogistics(Request $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        return response()->json(['data' => $this->logistics->present($this->logisticsFor($request), true, Gate::allows(EditionPermission::ViewSpeakerPrivateData->value))]);
    }

    public function updateLogistics(OrganizationLogisticsRequest $request): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $logistics = $this->logisticsFor($request);
        $logistics->fill($request->validated())->save();

        return response()->json(['data' => $this->logistics->present($logistics, true, Gate::allows(EditionPermission::ViewSpeakerPrivateData->value))]);
    }

    private function logisticsFor(Request $request): SpeakerLogistics
    {
        $userId = $request->route('user');
        $editionId = (string) $this->currentEdition->id();

        abort_unless(is_string($userId), 404);

        $speaks = Proposal::query()->where('decision', ProposalDecision::Accepted)->whereHas('speakers', fn (Builder $query) => $query->where('user_id', $userId))->exists();

        abort_unless($speaks, 404);

        return SpeakerLogistics::query()->firstOrCreate(['edition_id' => $editionId, 'user_id' => $userId]);
    }
}
