<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    public static function isValid(string $value): bool
    {
        $cpf = self::digits($value);

        if (mb_strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($index = 0; $index < $length; $index++) {
                $sum += (int) $cpf[$index] * ($length + 1 - $index);
            }

            if ((int) $cpf[$length] !== (($sum * 10) % 11) % 10) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !self::isValid($value)) {
            $fail('Informe um CPF válido.');
        }
    }
}
