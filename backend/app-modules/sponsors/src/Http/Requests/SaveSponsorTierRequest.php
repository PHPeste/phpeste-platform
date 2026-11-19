<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSponsorTierRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:80'],
            'price_cents' => [$required, 'integer', 'min:0', 'max:100000000000'],
            'slots' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'benefits' => ['sometimes', 'array', 'max:50'],
            'benefits.*' => ['string', 'min:2', 'max:200', 'distinct'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array{name?: string, price_cents?: int, slots?: int|null, benefits?: list<string>, position?: int}
     */
    public function tier(): array
    {
        /** @var array{name?: string, price_cents?: int, slots?: int|null, benefits?: list<string>, position?: int} */
        return $this->safe()->only(['name', 'price_cents', 'slots', 'benefits', 'position']);
    }
}
