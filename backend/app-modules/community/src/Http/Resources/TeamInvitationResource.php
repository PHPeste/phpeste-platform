<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Community\Models\EditionInvitation;

class TeamInvitationResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;

        if (!$invitation instanceof EditionInvitation) {
            return [];
        }

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'name' => $invitation->name,
            'role' => $invitation->role->value,
            'role_label' => $invitation->role->label(),
            'expires_at' => $this->isoDate($invitation->expires_at),
            'created_at' => $this->isoDate($invitation->created_at),
        ];
    }
}
