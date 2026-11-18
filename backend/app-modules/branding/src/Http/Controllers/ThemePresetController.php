<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Controllers;

use Illuminate\Http\JsonResponse;
use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Theme\ContrastChecker;

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
        ]);
    }
}
