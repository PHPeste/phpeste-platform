<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MapTicketImportRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['integer', 'min:0', 'distinct'],
            'ticket_type_map' => ['sometimes', 'array'],
            'ticket_type_map.*.value' => ['required', 'string', 'max:191'],
            'ticket_type_map.*.ticket_type_id' => ['present', 'nullable', 'uuid'],
            'default_ticket_type_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mapping.*.distinct' => 'Cada coluna alimenta um campo só.',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function mapping(): array
    {
        $mapping = [];

        foreach ((array) $this->input('mapping', []) as $field => $column) {
            if (is_string($field) && is_numeric($column)) {
                $mapping[$field] = (int) $column;
            }
        }

        return $mapping;
    }

    /**
     * @return array<string, string|null>
     */
    public function ticketTypeMap(): array
    {
        $map = [];

        foreach ((array) $this->input('ticket_type_map', []) as $entry) {
            if (is_array($entry) && is_string($entry['value'] ?? null)) {
                $id = $entry['ticket_type_id'] ?? null;
                $map[$entry['value']] = is_string($id) ? $id : null;
            }
        }

        return $map;
    }

    public function defaultTicketTypeId(): ?string
    {
        $id = $this->input('default_ticket_type_id');

        return is_string($id) && $id !== '' ? $id : null;
    }
}
