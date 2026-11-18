<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Branding\Enums\ThemeFont;
use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Theme\ContrastChecker;
use PHPeste\Branding\Theme\ThemeTokens;

class ThemePresetController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (ThemePreset $preset): array => [
                'id' => $preset->value,
                'label' => $preset->label(),
                'description' => $preset->description(),
                'theme' => $preset->theme(),
                'contrast' => ContrastChecker::report($preset->colors()),
            ], ThemePreset::cases()),
            'meta' => [
                'tokens' => array_map(fn (string $token, string $label): array => ['id' => $token, 'label' => $label], array_keys(ThemeTokens::COLORS), ThemeTokens::COLORS),
                'contrast_pairs' => ThemeTokens::contrastPairs(),
                'fonts' => array_map(fn (ThemeFont $font): string => $font->value, ThemeFont::cases()),
                'radius' => ['min' => 0, 'max' => 24],
            ],
        ]);
    }
}
