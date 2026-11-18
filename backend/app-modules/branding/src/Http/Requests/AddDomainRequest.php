<?php

declare(strict_types=1);

namespace PHPeste\Branding\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use PHPeste\Branding\Domains\Hostname;

class AddDomainRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:253', function (string $attribute, mixed $value, Closure $fail): void {
                if (!is_string($value) || Hostname::normalize($value) === null) {
                    $fail('Informe só o domínio, sem caminho, como 2027.phpeste.dev ou phpeste.com.br.');
                }
            }],
        ];
    }

    public function host(): string
    {
        return (string) Hostname::normalize($this->string('host')->toString());
    }
}
