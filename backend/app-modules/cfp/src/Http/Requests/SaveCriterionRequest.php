<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCriterionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'weight' => [$creating ? 'required' : 'sometimes', 'integer', 'between:1,5'],
            'position' => ['sometimes', 'integer', 'between:0,1000'],
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{name?: string, description?: string|null, weight?: int, position?: int}
     */
    public function criterion(): array
    {
        /** @var array{name?: string, description?: string|null, weight?: int, position?: int} $values */
        $values = collect($this->validated())->except('confirm')->all();

        return $values;
    }
}
