<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Branding\Events\ThemePublished;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\PublicTheme;

class RevertTheme
{
    public function handle(EditionSetting $settings): EditionSetting
    {
        $previous = $settings->theme_previous;

        if ($previous === null) {
            throw ValidationException::withMessages([
                'theme' => 'Não há identidade publicada anterior para restaurar.',
            ]);
        }

        DB::transaction(function () use ($settings, $previous): void {
            $settings->update([
                'theme_draft' => $previous,
                'theme_published' => $previous,
                'theme_previous' => null,
                'theme_published_at' => now(),
            ]);

            event(new ThemePublished($settings->edition_id));
        });

        PublicTheme::forget($settings->edition_id);

        return $settings;
    }
}
