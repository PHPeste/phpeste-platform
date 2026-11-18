<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Cfp\Actions\RespondToConfirmation;
use PHPeste\Cfp\Confirmations\LogisticsPresenter;
use PHPeste\Cfp\Http\Requests\RespondConfirmationRequest;
use PHPeste\Cfp\Http\Requests\SpeakerLogisticsRequest;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Models\SpeakerConfirmation;
use PHPeste\Cfp\Models\SpeakerLogistics;
use PHPeste\Community\Tenancy\CurrentEdition;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SpeakerConfirmationController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly LogisticsPresenter $logistics,
    ) {}

    public function confirm(RespondConfirmationRequest $request, RespondToConfirmation $respond): JsonResponse
    {
        return $this->respond($request, $respond, RespondToConfirmation::CONFIRM);
    }

    public function decline(RespondConfirmationRequest $request, RespondToConfirmation $respond): JsonResponse
    {
        return $this->respond($request, $respond, RespondToConfirmation::DECLINE);
    }

    public function withdraw(RespondConfirmationRequest $request, RespondToConfirmation $respond): JsonResponse
    {
        return $this->respond($request, $respond, RespondToConfirmation::WITHDRAW);
    }

    public function showLogistics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->logistics->present($this->ownLogistics($request), false)]);
    }

    public function updateLogistics(SpeakerLogisticsRequest $request): JsonResponse
    {
        $logistics = $this->ownLogistics($request);
        $logistics->fill($request->validated())->save();

        return response()->json(['data' => $this->logistics->present($logistics, false)]);
    }

    private function respond(RespondConfirmationRequest $request, RespondToConfirmation $respond, string $response): JsonResponse
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $id = $request->route('proposal');

        abort_unless(is_string($id), 404);

        $proposal = Proposal::query()->with('speakers')->whereKey($id)->firstOrFail();

        abort_unless($proposal->hasSpeaker($userId), 404);

        throw_if($proposal->ownerId() !== $userId, AccessDeniedHttpException::class, 'Só quem criou a proposta responde pela participação.');

        $confirmation = SpeakerConfirmation::query()->where('proposal_id', $proposal->id)->firstOrFail();
        $confirmation = $respond->handle($confirmation, $response, $request->filled('reason') ? $request->string('reason')->toString() : null, $userId);

        return response()->json(['data' => [
            'status' => $confirmation->status->value,
            'status_label' => $confirmation->status->label(),
            'deadline_at' => $confirmation->deadline_at->clone()->utc()->toIso8601ZuluString(),
        ]]);
    }

    private function ownLogistics(Request $request): SpeakerLogistics
    {
        return SpeakerLogistics::query()->firstOrCreate([
            'edition_id' => (string) $this->currentEdition->id(),
            'user_id' => (string) $request->user()?->getAuthIdentifier(),
        ]);
    }
}
