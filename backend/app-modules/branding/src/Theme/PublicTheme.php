<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use Illuminate\Support\Facades\Cache;
use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Models\EditionSetting;

final class PublicTheme
{
    /**
     * @return array{edition_id: string, theme: array{colors: array<string, string>, fonts: array{heading: string, body: string}, radius: int, logos: array<string, string|null>}, published_at: string|null}
     */
    public static function for(string $editionId): array
    {
        return Cache::rememberForever(self::cacheKey($editionId), function () use ($editionId): array {
            $settings = EditionSetting::query()->forEdition($editionId)->first();

            return [
                'edition_id' => $editionId,
                'theme' => ThemePresenter::present($settings->theme_published ?? ThemeTokens::fromPreset(ThemePreset::Sertao)),
                'published_at' => $settings?->theme_published_at?->clone()->utc()->toIso8601ZuluString(),
            ];
        });
    }

    public static function forget(string $editionId): void
    {
        Cache::forget(self::cacheKey($editionId));
    }

    private static function cacheKey(string $editionId): string
    {
        return "branding:public-theme:{$editionId}";
    }
}
