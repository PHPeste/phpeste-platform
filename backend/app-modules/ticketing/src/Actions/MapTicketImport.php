<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\ImportField;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Imports\ImportPreview;
use PHPeste\Ticketing\Imports\RowParser;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketType;

/**
 * Grava qual coluna alimenta cada campo e qual tipo de ingresso desta edição
 * corresponde a cada valor da coluna de tipo. Com tudo ligado, a importação
 * fica pronta para começar.
 */
class MapTicketImport
{
    public function __construct(private readonly ImportPreview $preview) {}

    /**
     * @param  array<string, int>  $mapping
     * @param  array<string, string|null>  $ticketTypeMap  valor da coluna => id do tipo, ou `null` para deixar essas linhas de fora
     */
    public function handle(TicketImport $import, array $mapping, array $ticketTypeMap, ?string $defaultTicketTypeId): TicketImport
    {
        if (!$import->status->editable()) {
            throw ValidationException::withMessages(['import' => 'Esta importação já começou; o mapeamento não muda mais.']);
        }

        $errors = [];
        $columns = count($import->headers);

        foreach ($mapping as $field => $column) {
            if (ImportField::tryFrom($field) === null) {
                $errors["mapping.{$field}"] = 'Campo desconhecido.';
            } elseif ($column < 0 || $column >= $columns) {
                $errors["mapping.{$field}"] = 'Coluna fora do arquivo.';
            }
        }

        foreach (ImportField::cases() as $field) {
            if ($field->required() && !isset($mapping[$field->value])) {
                $errors["mapping.{$field->value}"] = sprintf('Escolha a coluna de "%s".', $field->label());
            }
        }

        $typeIds = TicketType::query()->pluck('id')->all();

        if ($defaultTicketTypeId !== null && !in_array($defaultTicketTypeId, $typeIds, true)) {
            $errors['default_ticket_type_id'] = 'Este tipo de ingresso não é desta edição.';
        }

        $map = [];

        foreach ($ticketTypeMap as $value => $typeId) {
            if ($typeId !== null && !in_array($typeId, $typeIds, true)) {
                $errors['ticket_type_map'] = sprintf('O tipo escolhido para "%s" não é desta edição.', $value);

                continue;
            }

            $map[RowParser::typeKey((string) $value)] = $typeId;
        }

        if (!isset($mapping[ImportField::TicketType->value]) && $defaultTicketTypeId === null) {
            $errors['default_ticket_type_id'] = 'Sem coluna de tipo de ingresso, escolha o tipo que todos vão receber.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $import->fill(['mapping' => $mapping, 'ticket_type_map' => $map, 'default_ticket_type_id' => $defaultTicketTypeId]);

        if ($defaultTicketTypeId === null) {
            $missing = array_values(array_map(fn (array $value): string => $value['value'], array_diff_key($this->preview->ticketTypeValues($import), $map)));

            if ($missing !== []) {
                throw ValidationException::withMessages(['ticket_type_map' => sprintf('Escolha o tipo de ingresso para: %s. Ou defina um tipo padrão.', implode(', ', array_map(fn (string $value): string => '"'.$value.'"', $missing)))]);
            }
        }

        $import->status = ImportStatus::Ready;
        $import->save();

        return $import;
    }
}
