<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Payments\Enums\RefundReason;

class RefundOrderRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'cancel_tickets' => ['sometimes', 'boolean'],
            'item_ids' => ['sometimes', 'nullable', 'array', 'min:1', 'prohibited_unless:cancel_tickets,true,1'],
            'item_ids.*' => ['uuid', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'item_ids.prohibited_unless' => 'Escolher ingressos só vale quando eles vão ser cancelados.',
        ];
    }

    /**
     * @return list<string>|null
     */
    public function itemIds(): ?array
    {
        $ids = $this->input('item_ids');

        return is_array($ids) ? array_values(array_filter($ids, is_string(...))) : null;
    }

    public function amount(): ?int
    {
        return $this->filled('amount_cents') ? $this->integer('amount_cents') : null;
    }
}
