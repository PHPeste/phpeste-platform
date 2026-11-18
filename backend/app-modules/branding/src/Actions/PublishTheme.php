<?php

declare(strict_types=1);

namespace PHPeste\Branding\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Branding\Events\ThemePublished;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\PublicTheme;

class PublishTheme
{
    public function handle(EditionSetting $settings): EditionSetting
    {
        DB::transaction(function () use ($settings): void {
            $settings->update([
                'theme_previous' => $settings->theme_published,
                'theme_published' => $settings->theme_draft,
                'theme_published_at' => now(),
            ]);

            event(new ThemePublished($settings->edition_id));
        });

        PublicTheme::forget($settings->edition_id);

        return $settings;
    }
}
