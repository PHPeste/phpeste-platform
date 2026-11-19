<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordQuoteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'description' => ['required', 'string', 'min:2', 'max:500'],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'valid_until' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:received_on'],
        ];
    }
}
