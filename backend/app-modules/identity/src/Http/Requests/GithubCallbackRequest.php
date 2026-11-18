<?php

declare(strict_types=1);

namespace PHPeste\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GithubCallbackRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'state' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
