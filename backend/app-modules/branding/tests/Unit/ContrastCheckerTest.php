<?php

declare(strict_types=1);

use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Theme\ContrastChecker;
use PHPeste\Branding\Theme\ThemeTokens;

it('calcula a razão de contraste WCAG', function (): void {
    expect(ContrastChecker::ratio('#000000', '#FFFFFF'))->toBe(21.0)
        ->and(ContrastChecker::ratio('#FFFFFF', '#FFFFFF'))->toBe(1.0)
        ->and(round(ContrastChecker::ratio('#3A1607', '#FBF4E4'), 1))->toBe(14.8);
});

it('não importa a ordem das cores', function (): void {
    expect(ContrastChecker::ratio('#6B2A12', '#FFF8E8'))->toBe(ContrastChecker::ratio('#FFF8E8', '#6B2A12'));
});

it('todo preset passa em todos os pares de contraste', function (ThemePreset $preset): void {
    $failing = array_filter(ContrastChecker::report($preset->colors()), fn (array $pair): bool => !$pair['passes']);

    expect($failing)->toBe([])
        ->and(ContrastChecker::report($preset->colors()))->toHaveCount(count(ThemeTokens::contrastPairs()));
})->with(ThemePreset::cases());

it('todo preset define todos os tokens de cor', function (ThemePreset $preset): void {
    expect(array_keys($preset->colors()))->toEqualCanonicalizing(array_keys(ThemeTokens::COLORS));
})->with(ThemePreset::cases());
