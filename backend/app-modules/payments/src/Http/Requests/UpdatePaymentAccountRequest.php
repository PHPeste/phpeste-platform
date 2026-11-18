<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'holder_name' => ['sometimes', 'string', 'min:3', 'max:120'],
            ...CreatePaymentAccountRequest::settingsRules(false),
        ];
    }
}
