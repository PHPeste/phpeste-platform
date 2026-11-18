<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Enums\CouponKind;
use PHPeste\Ticketing\Models\Coupon;

class SaveCouponRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $couponId = $this->route('coupon');
        $editionId = resolve(CurrentEdition::class)->id();

        return [
            'code' => [$creating ? 'required' : 'sometimes', 'string', 'alpha_dash:ascii', 'min:3', 'max:40', Rule::unique('coupons', 'code')
                ->where('edition_id', $editionId)
                ->ignore(is_string($couponId) ? $couponId : null)],
            'description' => ['sometimes', 'nullable', 'string', 'max:200'],
            'kind' => [$creating ? 'required' : 'sometimes', Rule::enum(CouponKind::class)],
            'value' => [$creating ? 'required' : 'sometimes', 'integer', 'min:1', ...($this->input('kind') === CouponKind::Percent->value ? ['max:100'] : ['max:10000000'])],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'between:1,100000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'ticket_type_ids' => ['sometimes', 'nullable', 'array'],
            'ticket_type_ids.*' => ['uuid', 'distinct', Rule::exists('ticket_types', 'id')->where('edition_id', $editionId)],
            'active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.max' => 'O desconto percentual vai de 1 a 100.',
            'ends_at.after' => 'O fim da validade precisa ser depois do início.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function coupon(): array
    {
        /** @var array<string, mixed> $values */
        $values = $this->validated();

        if (isset($values['code']) && is_string($values['code'])) {
            $values['code'] = Coupon::normalize($values['code']);
        }

        if (array_key_exists('ticket_type_ids', $values)) {
            $ids = is_array($values['ticket_type_ids']) ? array_values(array_filter($values['ticket_type_ids'], is_string(...))) : [];
            $values['ticket_type_ids'] = $ids === [] ? null : $ids;
        }

        return $values;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_string($code)) {
            $this->merge(['code' => Coupon::normalize($code)]);
        }
    }
}
