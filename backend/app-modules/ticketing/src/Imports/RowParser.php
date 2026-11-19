<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use PHPeste\Ticketing\Enums\ImportField;
use PHPeste\Ticketing\Models\TicketImport;

/**
 * Transforma os valores de uma linha do CSV em `ImportRow`, ou devolve o
 * motivo, em português, de a linha não poder entrar.
 */
final class RowParser
{
    public static function typeKey(string $value): string
    {
        return mb_strtolower(mb_trim($value));
    }

    /**
     * @param  list<string>  $values
     */
    public function parse(TicketImport $import, array $values): ImportRow|string
    {
        $get = fn (ImportField $field): string => ($column = $import->column($field)) === null ? '' : ($values[$column] ?? '');

        $name = $get(ImportField::Name);
        $email = mb_strtolower($get(ImportField::Email));

        if ($name === '') {
            return 'Sem nome do participante.';
        }

        if (mb_strlen($name) > 120) {
            return 'Nome do participante com mais de 120 caracteres.';
        }

        if ($email === '') {
            return 'Sem e-mail do participante.';
        }

        if (!$this->validEmail($email)) {
            return sprintf('E-mail inválido: "%s".', $email);
        }

        $typeId = $this->ticketType($import, $get(ImportField::TicketType));

        if ($typeId === null) {
            return 'Sem tipo de ingresso.';
        }

        if (is_array($typeId)) {
            return $typeId[1]
                ? sprintf('Linhas do tipo "%s" ficaram de fora da importação.', $typeId[0])
                : sprintf('Tipo de ingresso "%s" sem correspondência nesta edição.', $typeId[0]);
        }

        $reference = $get(ImportField::OrderReference);
        $code = $get(ImportField::TicketCode);

        if (mb_strlen($reference) > 100 || mb_strlen($code) > 100) {
            return 'Número do pedido ou código do ingresso com mais de 100 caracteres.';
        }

        $buyerEmail = mb_strtolower($get(ImportField::BuyerEmail));

        if ($buyerEmail !== '' && !$this->validEmail($buyerEmail)) {
            return sprintf('E-mail de quem comprou inválido: "%s".', $buyerEmail);
        }

        $buyerName = $get(ImportField::BuyerName);
        $badge = $get(ImportField::BadgeName);
        $company = $get(ImportField::Company);

        return new ImportRow(
            name: $name,
            email: $email,
            ticketTypeId: $typeId,
            orderReference: $reference === '' ? null : $reference,
            ticketCode: $code === '' ? null : $code,
            buyerName: $buyerName === '' ? $name : mb_substr($buyerName, 0, 120),
            buyerEmail: $buyerEmail === '' ? $email : $buyerEmail,
            badgeName: $badge === '' ? null : mb_substr($badge, 0, 60),
            company: $company === '' ? null : mb_substr($company, 0, 120),
        );
    }

    private function validEmail(string $email): bool
    {
        return mb_strlen($email) <= 191 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return string|array{0: string, 1: bool}|null id do tipo; `[valor, deixado de fora?]` quando não há tipo; `null` sem valor nem padrão
     */
    private function ticketType(TicketImport $import, string $value): string|array|null
    {
        if ($import->column(ImportField::TicketType) === null || $value === '') {
            return $import->default_ticket_type_id;
        }

        $map = $import->ticket_type_map ?? [];
        $key = self::typeKey($value);

        if (array_key_exists($key, $map)) {
            return $map[$key] ?? [$value, true];
        }

        return $import->default_ticket_type_id ?? [$value, false];
    }
}
