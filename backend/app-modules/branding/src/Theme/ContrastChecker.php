<?php

declare(strict_types=1);

namespace PHPeste\Branding\Theme;

final class ContrastChecker
{
    public static function ratio(string $foreground, string $background): float
    {
        $lighter = max(self::luminance($foreground), self::luminance($background));
        $darker = min(self::luminance($foreground), self::luminance($background));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * @param  array<string, string>  $colors
     * @return list<array{foreground: string, background: string, ratio: float, minimum: float, passes: bool}>
     */
    public static function report(array $colors): array
    {
        $report = [];

        foreach (ThemeTokens::contrastPairs() as $pair) {
            $foreground = $colors[$pair['foreground']] ?? null;
            $background = $colors[$pair['background']] ?? null;

            if ($foreground === null || $background === null) {
                continue;
            }

            $ratio = round(self::ratio($foreground, $background), 2);

            $report[] = [
                'foreground' => $pair['foreground'],
                'background' => $pair['background'],
                'ratio' => $ratio,
                'minimum' => $pair['minimum'],
                'passes' => $ratio >= $pair['minimum'],
            ];
        }

        return $report;
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.039_28 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            mb_str_split(mb_ltrim($hex, '#'), 2),
        );

        return 0.212_6 * $channels[0] + 0.715_2 * $channels[1] + 0.072_2 * $channels[2];
    }
}
