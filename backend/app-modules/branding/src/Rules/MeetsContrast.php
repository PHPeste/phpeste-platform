<?php

declare(strict_types=1);

namespace PHPeste\Branding\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPeste\Branding\Theme\ContrastChecker;
use PHPeste\Branding\Theme\ThemeTokens;

class MeetsContrast implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_array($value)) {
            return;
        }

        $colors = array_filter(
            $value,
            fn (mixed $color, int|string $token): bool => is_string($token) && is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1,
            ARRAY_FILTER_USE_BOTH,
        );

        foreach (ContrastChecker::report($colors) as $pair) {
            if ($pair['passes']) {
                continue;
            }

            $fail(sprintf(
                'Contraste insuficiente entre %s (%s) e %s (%s): %s:1, o mínimo é %s:1.',
                ThemeTokens::COLORS[$pair['foreground']],
                $pair['foreground'],
                ThemeTokens::COLORS[$pair['background']],
                $pair['background'],
                number_format($pair['ratio'], 2, ',', ''),
                number_format($pair['minimum'], 1, ',', ''),
            ));
        }
    }
}
