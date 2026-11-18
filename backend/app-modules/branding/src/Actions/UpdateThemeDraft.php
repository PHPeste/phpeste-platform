<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use PHPeste\Branding\Models\EditionSetting;

class UpdateThemeDraft
{
    /**
     * @param  array<string, string>  $colors
     * @param  array{heading: string, body: string}  $fonts
     */
    public function handle(EditionSetting $settings, array $colors, array $fonts, int $radius): EditionSetting
    {
        $settings->update([
            'theme_draft' => [
                ...$settings->theme_draft,
                'colors' => $colors,
                'fonts' => $fonts,
                'radius' => $radius,
            ],
        ]);

        return $settings;
    }
}
