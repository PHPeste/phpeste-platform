<?php

declare(strict_types=1);

namespace PHPeste\Community\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Cloning\EditionCloners;

class CloneEditionRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parts' => ['present', 'array'],
            'parts.*' => ['string', Rule::in(resolve(EditionCloners::class)->keys())],
        ];
    }

    /**
     * @return list<string>
     */
    public function parts(): array
    {
        $parts = [];

        foreach ((array) $this->input('parts', []) as $part) {
            if (is_string($part)) {
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
