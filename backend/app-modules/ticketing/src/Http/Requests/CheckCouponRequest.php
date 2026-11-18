<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckCouponRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'items' => ['required', 'array', 'min:1', 'max:10'],
            'items.*.ticket_type_id' => ['required', 'uuid', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return list<array{ticket_type_id: string, quantity: int}>
     */
    public function items(): array
    {
        $items = [];

        foreach ((array) $this->validated('items') as $item) {
            if (is_array($item) && is_string($item['ticket_type_id'] ?? null) && is_numeric($item['quantity'] ?? null)) {
                $items[] = ['ticket_type_id' => $item['ticket_type_id'], 'quantity' => (int) $item['quantity']];
            }
        }

        return $items;
    }
}
