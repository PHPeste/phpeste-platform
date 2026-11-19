<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveContactRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:2', 'max:120'],
            'role' => ['sometimes', 'nullable', 'string', 'max:80'],
            'email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function contact(): array
    {
        $data = [];

        foreach (['name', 'role', 'email', 'phone'] as $key) {
            if ($this->has($key)) {
                $data[$key] = $this->filled($key) ? $this->string($key)->trim()->toString() : null;
            }
        }

        return $data;
    }
}
