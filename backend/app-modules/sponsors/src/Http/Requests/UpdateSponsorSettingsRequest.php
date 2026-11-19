<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSponsorSettingsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'goal_cents' => ['present', 'nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }
}
