<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderColumnsRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:30'],
            'ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_values(array_filter((array) $this->input('ids'), is_string(...)));
    }
}
