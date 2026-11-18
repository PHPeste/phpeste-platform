<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\ThemeTokens;

class DiscardThemeDraft
{
    public function handle(EditionSetting $settings): EditionSetting
    {
        $settings->update([
            'theme_draft' => $settings->theme_published ?? ThemeTokens::fromPreset(ThemePreset::Sertao),
        ]);

        return $settings;
    }
}
