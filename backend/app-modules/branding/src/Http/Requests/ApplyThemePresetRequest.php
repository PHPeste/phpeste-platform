<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Branding\Enums\ThemePreset;

class ApplyThemePresetRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'preset' => ['required', Rule::enum(ThemePreset::class)],
        ];
    }
}
