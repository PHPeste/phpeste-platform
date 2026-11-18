<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Enums\BrazilianState;

class SpeakerLogisticsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'origin_city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'origin_state' => ['sometimes', 'nullable', Rule::enum(BrazilianState::class)],
            'needs_travel' => ['sometimes', 'boolean'],
            'needs_lodging' => ['sometimes', 'boolean'],
        ];
    }
}
