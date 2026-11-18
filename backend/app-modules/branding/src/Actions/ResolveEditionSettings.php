<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\ThemeTokens;

class ResolveEditionSettings
{
    public function handle(): EditionSetting
    {
        return EditionSetting::query()->firstOrCreate([], [
            'theme_draft' => ThemeTokens::fromPreset(ThemePreset::Sertao),
        ]);
    }
}
