<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use Illuminate\Support\Facades\Storage;

/**
 * @phpstan-import-type Theme from ThemeTokens
 */
final class ThemePresenter
{
    public const string DISK = 'public';

    /**
     * @param  Theme  $theme
     * @return array{colors: array<string, string>, fonts: array{heading: string, body: string}, radius: int, logos: array<string, string|null>}
     */
    public static function present(array $theme): array
    {
        $disk = Storage::disk(self::DISK);

        return [
            ...$theme,
            'logos' => array_map(fn (?string $path): ?string => $path === null ? null : $disk->url($path), $theme['logos']),
        ];
    }
}
