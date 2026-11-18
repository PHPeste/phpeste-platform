<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\ContrastChecker;
use PHPeste\Branding\Theme\ThemePresenter;

class ThemeSettingsResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $settings = $this->resource;

        if (!$settings instanceof EditionSetting) {
            return [];
        }

        return [
            'edition_id' => $settings->edition_id,
            'draft' => ThemePresenter::present($settings->theme_draft),
            'published' => $settings->theme_published === null ? null : ThemePresenter::present($settings->theme_published),
            'published_at' => $this->isoDate($settings->theme_published_at),
            'has_unpublished_changes' => $settings->hasUnpublishedChanges(),
            'can_revert' => $settings->theme_previous !== null,
            'contrast' => ContrastChecker::report($settings->theme_draft['colors']),
        ];
    }
}
