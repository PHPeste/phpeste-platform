<?php

declare(strict_types=1);

namespace PHPeste\Payments\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use PHPeste\Payments\Enums\HolderType;

class TaxDocument implements ValidationRule
{
    public function __construct(private readonly ?HolderType $type) {}

    public static function digits(string $value): string
    {
        return (string) preg_replace('/\D/', '', $value);
    }

    public static function mask(string $value): string
    {
        $digits = self::digits($value);

        return mb_strlen($digits) === 14
            ? '**.***.'.mb_substr($digits, 5, 3).'/'.mb_substr($digits, 8, 4).'-**'
            : '***.***.'.mb_substr($digits, 6, 3).'-**';
    }

    public static function isCpf(string $value): bool
    {
        $digits = self::digits($value);

        return mb_strlen($digits) === 11 && self::checkDigits($digits, [[10, 9, 8, 7, 6, 5, 4, 3, 2], [11, 10, 9, 8, 7, 6, 5, 4, 3, 2]]);
    }

    public static function isCnpj(string $value): bool
    {
        $digits = self::digits($value);

        return mb_strlen($digits) === 14 && self::checkDigits($digits, [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]]);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('Informe o documento do titular.');

            return;
        }

        if ($this->type === HolderType::Company && !self::isCnpj($value)) {
            $fail('Informe um CNPJ válido.');
        }

        if ($this->type !== HolderType::Company && !self::isCpf($value)) {
            $fail('Informe um CPF válido.');
        }
    }

    /**
     * @param  list<list<int>>  $weights
     */
    private static function checkDigits(string $digits, array $weights): bool
    {
        if (preg_match('/^(\d)\1+$/', $digits) === 1) {
            return false;
        }

        $base = mb_strlen($digits) - 2;

        foreach ($weights as $round => $factors) {
            $sum = 0;

            foreach ($factors as $index => $factor) {
                $sum += (int) $digits[$index] * $factor;
            }

            $rest = $sum % 11;
            $expected = $rest < 2 ? 0 : 11 - $rest;

            if ((int) $digits[$base + $round] !== $expected) {
                return false;
            }
        }

        return true;
    }
}
