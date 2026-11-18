<?php

declare(strict_types=1);

namespace PHPeste\Branding\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;

class EditionSettingPolicy
{
    public function __construct(private readonly EditionMemberships $memberships) {}

    public function view(Authenticatable $user, EditionSetting $settings): bool
    {
        return $this->memberships->roleIn($settings->edition_id, (string) $user->getAuthIdentifier()) instanceof EditionRole;
    }

    public function update(Authenticatable $user, EditionSetting $settings): bool
    {
        return $this->memberships->can($settings->edition_id, (string) $user->getAuthIdentifier(), EditionPermission::ManageEdition);
    }
}
