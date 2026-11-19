<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveColumnRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:1', 'max:60'],
            'is_done' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, is_done?: bool}
     */
    public function column(): array
    {
        $data = [];

        if ($this->filled('name')) {
            $data['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->has('is_done')) {
            $data['is_done'] = $this->boolean('is_done');
        }

        return $data;
    }
}
