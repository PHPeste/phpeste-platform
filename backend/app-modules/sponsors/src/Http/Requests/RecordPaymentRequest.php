<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
