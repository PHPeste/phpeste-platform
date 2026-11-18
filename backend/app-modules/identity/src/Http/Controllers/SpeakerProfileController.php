<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Actions\SaveSpeakerProfile;
use PHPeste\Identity\Contracts\EditionParticipation;
use PHPeste\Identity\Http\Requests\SaveSpeakerProfileRequest;
use PHPeste\Identity\Http\Resources\SpeakerProfileResource;
use PHPeste\Identity\Models\User;

class SpeakerProfileController
{
    public function show(Request $request): SpeakerProfileResource
    {
        return new SpeakerProfileResource($this->user($request)->load(['speakerProfile', 'speakerPrivateDetails']));
    }

    public function update(SaveSpeakerProfileRequest $request, SaveSpeakerProfile $save): SpeakerProfileResource
    {
        return new SpeakerProfileResource($save->handle($this->user($request), $request->profile(), $request->privateDetails()));
    }

    public function showForEdition(Request $request, CurrentEdition $currentEdition): SpeakerProfileResource
    {
        Gate::authorize(EditionPermission::ManageProgram->value);

        $userId = $request->route('user');
        $editionId = (string) $currentEdition->id();

        abort_unless(is_string($userId) && $this->participates($editionId, $userId), 404);

        return new SpeakerProfileResource(User::query()->with(['speakerProfile', 'speakerPrivateDetails'])->findOrFail($userId));
    }

    private function participates(string $editionId, string $userId): bool
    {
        foreach (app()->tagged(EditionParticipation::TAG) as $participation) {
            if ($participation instanceof EditionParticipation && $participation->participates($editionId, $userId)) {
                return true;
            }
        }

        return false;
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
