<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Branding\Enums\ThemeFont;
use PHPeste\Branding\Rules\MeetsContrast;
use PHPeste\Branding\Theme\ThemeTokens;

class UpdateThemeDraftRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tokens = array_keys(ThemeTokens::COLORS);

        $colorRules = [];

        foreach ($tokens as $token) {
            $colorRules["colors.{$token}"] = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }

        return [
            'colors' => ['required', 'array:'.implode(',', $tokens), new MeetsContrast],
            ...$colorRules,
            'fonts' => ['required', 'array:heading,body'],
            'fonts.heading' => ['required', Rule::enum(ThemeFont::class)],
            'fonts.body' => ['required', Rule::enum(ThemeFont::class)],
            'radius' => ['required', 'integer', 'between:0,24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function colors(): array
    {
        $colors = [];

        foreach (array_keys(ThemeTokens::COLORS) as $token) {
            $colors[$token] = mb_strtoupper($this->string("colors.{$token}")->toString());
        }

        return $colors;
    }

    /**
     * @return array{heading: string, body: string}
     */
    public function fonts(): array
    {
        return [
            'heading' => $this->string('fonts.heading')->toString(),
            'body' => $this->string('fonts.body')->toString(),
        ];
    }
}
