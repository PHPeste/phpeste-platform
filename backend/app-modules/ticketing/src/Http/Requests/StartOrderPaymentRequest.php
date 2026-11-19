<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Payments\Enums\PaymentMethod;

class StartOrderPaymentRequest extends FormRequest
{
    public const array CARD_FIELDS = ['card', 'card_number', 'number', 'cvv', 'ccv', 'cvc', 'expiry', 'expiry_month', 'expiry_year', 'holder_name'];

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'installments' => ['sometimes', 'integer', 'between:1,21'],
            'card_token' => ['sometimes', 'nullable', 'string', 'max:200'],
            'card_brand' => ['sometimes', 'nullable', 'string', 'max:40', 'alpha_dash'],
            'card_issuer' => ['sometimes', 'nullable', 'string', 'max:20', 'alpha_num'],
        ];

        foreach (self::CARD_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (self::CARD_FIELDS as $field) {
            $messages["{$field}.prohibited"] = 'Os dados do cartão nunca são enviados para a plataforma: eles são digitados só na página do meio de pagamento.';
        }

        return $messages;
    }
}
