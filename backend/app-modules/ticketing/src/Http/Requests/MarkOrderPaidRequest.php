<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkOrderPaidRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'justification' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'justification.required' => 'Explique como o pagamento foi recebido.',
            'justification.min' => 'Explique como o pagamento foi recebido, com pelo menos 10 caracteres.',
        ];
    }

    public function justification(): string
    {
        return mb_trim((string) $this->string('justification'));
    }
}
