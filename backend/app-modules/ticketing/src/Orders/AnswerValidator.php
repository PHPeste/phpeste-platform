<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use PHPeste\Ticketing\Models\TicketType;

class AnswerValidator
{
    /**
     * @param  array<string, mixed>  $answers
     * @return array{0: array<string, string|bool>, 1: array<string, string>}
     */
    public function validate(TicketType $type, array $answers, string $prefix): array
    {
        $clean = [];
        $errors = [];

        foreach ($type->questions ?? [] as $question) {
            $id = $question['id'];
            $value = $answers[$id] ?? null;
            $key = "{$prefix}.answers.{$id}";
            $empty = $value === null || (is_string($value) && mb_trim($value) === '');

            if ($empty) {
                if ($question['required']) {
                    $errors[$key] = sprintf('Responda "%s".', $question['label']);
                }

                continue;
            }

            if ($question['type'] === 'boolean') {
                if (!is_bool($value)) {
                    $errors[$key] = sprintf('Responda "%s" com sim ou não.', $question['label']);

                    continue;
                }

                $clean[$id] = $value;

                continue;
            }

            if (!is_string($value) || mb_strlen($value) > 200) {
                $errors[$key] = sprintf('A resposta de "%s" deve ter até 200 caracteres.', $question['label']);

                continue;
            }

            if ($question['type'] === 'select' && !in_array($value, $question['options'], true)) {
                $errors[$key] = sprintf('Escolha uma das opções de "%s".', $question['label']);

                continue;
            }

            $clean[$id] = mb_trim($value);
        }

        return [$clean, $errors];
    }
}
