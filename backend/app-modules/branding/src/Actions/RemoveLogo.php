<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Enums\LogoSlot;
use PHPeste\Branding\Models\EditionSetting;

class RemoveLogo
{
    public function handle(EditionSetting $settings, LogoSlot $slot): EditionSetting
    {
        $settings->update([
            'theme_draft' => [
                ...$settings->theme_draft,
                'logos' => [...$settings->theme_draft['logos'], $slot->value => null],
            ],
        ]);

        return $settings;
    }
}
