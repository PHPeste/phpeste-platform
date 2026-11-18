<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Ticketing\Enums\SellOutBehavior;

class SaveTicketBatchRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80'],
            'price_cents' => [$creating ? 'required' : 'sometimes', 'integer', 'between:0,10000000'],
            'quantity' => [$creating ? 'required' : 'sometimes', 'integer', 'between:1,100000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'on_sell_out' => ['sometimes', Rule::enum(SellOutBehavior::class)],
            'position' => ['sometimes', 'integer', 'between:0,1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ends_at.after' => 'O fim das vendas precisa ser depois do início.'];
    }
}
