<?php

declare(strict_types=1);

namespace PHPeste\Community\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Models\EditionInvitation;

class ResendInvitation
{
    public function __construct(private readonly InviteTeamMember $invite) {}

    public function handle(EditionInvitation $invitation): EditionInvitation
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw ValidationException::withMessages([
                'invitation' => 'Só é possível reenviar um convite que ainda não foi aceito nem cancelado.',
            ]);
        }

        $token = Str::random(48);

        $invitation->update([
            'token_hash' => EditionInvitation::hashToken($token),
            'expires_at' => now()->addDays(InviteTeamMember::VALID_FOR_DAYS),
        ]);

        $this->invite->send($invitation, $token, $invitation->name);

        return $invitation;
    }
}
