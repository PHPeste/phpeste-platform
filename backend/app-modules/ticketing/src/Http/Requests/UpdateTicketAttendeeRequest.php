<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Ticketing\Enums\TshirtSize;

class UpdateTicketAttendeeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'holder_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'badge_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tshirt_size' => ['sometimes', 'nullable', Rule::enum(TshirtSize::class)],
            'accessibility' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['holder_name', 'badge_name', 'company', 'accessibility'] as $field) {
            if (is_string($this->input($field))) {
                $value = mb_trim($this->input($field));
                $this->merge([$field => $value === '' && $field !== 'holder_name' ? null : $value]);
            }
        }
    }
}
