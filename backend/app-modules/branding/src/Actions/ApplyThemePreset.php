<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Models\EditionSetting;

class ApplyThemePreset
{
    public function handle(EditionSetting $settings, ThemePreset $preset): EditionSetting
    {
        $settings->update([
            'theme_draft' => [
                ...$settings->theme_draft,
                ...$preset->theme(),
            ],
        ]);

        return $settings;
    }
}
