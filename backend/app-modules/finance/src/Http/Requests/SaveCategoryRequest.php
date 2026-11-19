<?php

declare(strict_types=1);

namespace PHPeste\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCategoryRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'min:2', 'max:80'],
            'budget_cents' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array{name?: string, budget_cents?: int|null, position?: int}
     */
    public function category(): array
    {
        $data = [];

        if ($this->filled('name')) {
            $data['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->has('budget_cents')) {
            $data['budget_cents'] = $this->filled('budget_cents') ? $this->integer('budget_cents') : null;
        }

        if ($this->filled('position')) {
            $data['position'] = $this->integer('position');
        }

        return $data;
    }
}
