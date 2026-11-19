<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use Illuminate\Support\Str;
use PHPeste\Ticketing\Enums\ImportField;

/**
 * Sugere qual coluna do CSV alimenta cada campo, pelo nome do cabeçalho.
 * É só a primeira sugestão da tela de mapeamento; quem importa confirma.
 */
final class ColumnGuesser
{
    /**
     * Os campos mais específicos vêm antes: "E-mail do comprador" não pode
     * ser pego pelo "e-mail" do participante, nem "Nome no crachá" pelo "nome".
     */
    private const array ORDER = [
        ImportField::BuyerEmail,
        ImportField::BuyerName,
        ImportField::OrderReference,
        ImportField::TicketCode,
        ImportField::BadgeName,
        ImportField::TicketType,
        ImportField::Email,
        ImportField::Name,
        ImportField::Company,
    ];

    public static function normalize(string $header): string
    {
        return (string) preg_replace('/\s+/', ' ', mb_strtolower(mb_trim(Str::ascii($header))));
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>
     */
    public function guess(array $headers): array
    {
        $normalized = array_map(self::normalize(...), $headers);
        $mapping = [];

        foreach (self::ORDER as $field) {
            $index = $this->find($field, $normalized, array_values($mapping));

            if ($index !== null) {
                $mapping[$field->value] = $index;
            }
        }

        return $mapping;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<int>  $taken
     */
    private function find(ImportField $field, array $headers, array $taken): ?int
    {
        foreach ($field->hints() as $hint) {
            foreach ($headers as $index => $header) {
                if (!in_array($index, $taken, true) && $header === $hint) {
                    return $index;
                }
            }
        }

        foreach ($field->hints() as $hint) {
            foreach ($headers as $index => $header) {
                if (!in_array($index, $taken, true) && str_contains($header, $hint)) {
                    return $index;
                }
            }
        }

        return null;
    }
}
