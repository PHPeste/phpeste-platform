<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

use PHPeste\Branding\Enums\LogoSlot;
use PHPeste\Branding\Enums\ThemePreset;

/**
 * @phpstan-type Theme array{colors: array<string, string>, fonts: array{heading: string, body: string}, radius: int, logos: array<string, string|null>}
 */
final class ThemeTokens
{
    public const array COLORS = [
        'bg' => 'Fundo',
        'surface' => 'Superfície',
        'surface_2' => 'Superfície secundária',
        'line' => 'Bordas',
        'ink' => 'Texto',
        'ink_2' => 'Texto secundário',
        'ink_3' => 'Legendas',
        'link' => 'Links',
        'brand' => 'Marca',
        'on_brand' => 'Texto sobre marca',
        'accent' => 'Destaque',
        'on_accent' => 'Texto sobre destaque',
        'support' => 'Apoio',
        'ok' => 'Sucesso',
        'ok_bg' => 'Fundo de sucesso',
        'warn' => 'Atenção',
        'warn_bg' => 'Fundo de atenção',
        'danger' => 'Erro',
        'danger_bg' => 'Fundo de erro',
        'info' => 'Informação',
        'info_bg' => 'Fundo de informação',
    ];

    public const float TEXT_MINIMUM = 4.5;

    public const float FOCUS_MINIMUM = 3.0;

    /**
     * @return Theme
     */
    public static function fromPreset(ThemePreset $preset): array
    {
        return [...$preset->theme(), 'logos' => self::emptyLogos()];
    }

    /**
     * @return array<string, string|null>
     */
    public static function emptyLogos(): array
    {
        return array_fill_keys(array_map(fn (LogoSlot $slot): string => $slot->value, LogoSlot::cases()), null);
    }

    /**
     * @return list<array{foreground: string, background: string, minimum: float}>
     */
    public static function contrastPairs(): array
    {
        $text = [
            ['ink', 'bg'], ['ink', 'surface'], ['ink', 'surface_2'],
            ['ink_2', 'bg'], ['ink_2', 'surface'], ['ink_2', 'surface_2'],
            ['ink_3', 'bg'], ['ink_3', 'surface'],
            ['link', 'bg'], ['link', 'surface'],
            ['on_brand', 'brand'], ['on_brand', 'support'], ['on_accent', 'accent'],
            ['ok', 'ok_bg'], ['warn', 'warn_bg'], ['danger', 'danger_bg'], ['info', 'info_bg'],
            ['ok', 'surface'], ['warn', 'surface'], ['danger', 'surface'], ['info', 'surface'],
        ];

        $focus = [['brand', 'bg'], ['brand', 'surface']];

        return [
            ...array_map(fn (array $pair): array => ['foreground' => $pair[0], 'background' => $pair[1], 'minimum' => self::TEXT_MINIMUM], $text),
            ...array_map(fn (array $pair): array => ['foreground' => $pair[0], 'background' => $pair[1], 'minimum' => self::FOCUS_MINIMUM], $focus),
        ];
    }
}
