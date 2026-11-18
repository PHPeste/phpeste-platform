<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Enums\HolderType;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Rules\TaxDocument;

class CreatePaymentAccountRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function settingsRules(bool $creating): array
    {
        return [
            'methods' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'methods.*' => ['distinct', Rule::enum(PaymentMethod::class)],
            'max_installments' => ['sometimes', 'integer', 'between:1,12'],
            'pix_expiration_minutes' => ['sometimes', 'integer', 'between:10,1440'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'driver' => ['required', Rule::in(resolve(GatewayRegistry::class)->keys())],
            'environment' => ['required', Rule::enum(GatewayEnvironment::class)],
            'api_key' => ['required', 'string', 'min:8', 'max:500'],
            'webhook_secret' => ['required', 'string', 'min:8', 'max:500'],
            'holder_type' => ['required', Rule::enum(HolderType::class)],
            'holder_name' => ['required', 'string', 'min:3', 'max:120'],
            'holder_document' => ['required', 'string', new TaxDocument(HolderType::tryFrom((string) $this->input('holder_type')))],
            ...self::settingsRules(true),
            'confirm' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['methods.required' => 'Escolha pelo menos um método de pagamento.', 'methods.min' => 'Escolha pelo menos um método de pagamento.'];
    }

    /**
     * @return array{driver: string, environment: string, api_key: string, webhook_secret: string, holder_type: string, holder_name: string, holder_document: string, methods: list<string>, max_installments: int, pix_expiration_minutes: int}
     */
    public function account(): array
    {
        return [
            'driver' => $this->string('driver')->toString(),
            'environment' => $this->string('environment')->toString(),
            'api_key' => $this->string('api_key')->trim()->toString(),
            'webhook_secret' => $this->string('webhook_secret')->trim()->toString(),
            'holder_type' => $this->string('holder_type')->toString(),
            'holder_name' => $this->string('holder_name')->trim()->toString(),
            'holder_document' => TaxDocument::digits($this->string('holder_document')->toString()),
            'methods' => array_values(array_filter((array) $this->validated('methods'), is_string(...))),
            'max_installments' => $this->integer('max_installments', 1),
            'pix_expiration_minutes' => $this->integer('pix_expiration_minutes', 30),
        ];
    }
}
