<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use Illuminate\Support\Facades\Storage;
use PHPeste\Ticketing\Actions\ProcessImportChunk;
use PHPeste\Ticketing\Enums\ImportField;
use PHPeste\Ticketing\Enums\ImportRowOutcome;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketType;

/**
 * O que a tela de mapeamento mostra antes de importar: as primeiras linhas
 * com o resultado de cada uma (sem gravar nada) e os valores distintos da
 * coluna de tipo de ingresso, para ligar cada um a um tipo desta edição.
 */
final readonly class ImportPreview
{
    public const int SAMPLE = 10;

    public function __construct(private RowEvaluator $evaluator) {}

    public static function file(TicketImport $import): ?CsvFile
    {
        if ($import->file_path === null) {
            return null;
        }

        return CsvFile::open(Storage::disk(ProcessImportChunk::disk())->path($import->file_path), $import->delimiter, $import->encoding);
    }

    /**
     * Valores distintos da coluna de tipo, com quantas linhas têm cada um.
     *
     * @return array<string, array{value: string, rows: int}>
     */
    public function ticketTypeValues(TicketImport $import): array
    {
        $column = $import->column(ImportField::TicketType);
        $file = self::file($import);

        if ($column === null || !$file instanceof CsvFile) {
            return [];
        }

        $values = [];

        foreach ($file->records($file->dataOffset()) as $record) {
            $value = $record['values'][$column] ?? '';

            if ($record['blank'] || $value === '') {
                continue;
            }

            $key = RowParser::typeKey($value);
            $values[$key] ??= ['value' => $value, 'rows' => 0];
            $values[$key]['rows']++;
        }

        ksort($values);

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function build(TicketImport $import): array
    {
        $file = self::file($import);
        $types = TicketType::query()->orderBy('position')->orderBy('name')->get(['id', 'name']);
        $byName = $types->keyBy(fn (TicketType $type): string => RowParser::typeKey($type->name));
        $sample = [];
        $summary = ['import' => 0, 'duplicate' => 0, 'rejected' => 0];

        if ($file instanceof CsvFile && $import->status->editable()) {
            foreach ($file->records($file->dataOffset()) as $record) {
                if ($record['blank']) {
                    continue;
                }

                $evaluation = $this->evaluator->evaluate($import, $record['values']);
                $summary[$evaluation->outcome->value]++;
                $sample[] = [
                    'line' => count($sample) + 1,
                    'values' => $record['values'],
                    'outcome' => $evaluation->outcome->value,
                    'reason' => $evaluation->outcome === ImportRowOutcome::Import ? null : $evaluation->reason,
                ];

                if (count($sample) >= self::SAMPLE) {
                    break;
                }
            }
        }

        return [
            'headers' => $import->headers,
            'fields' => array_map(fn (ImportField $field): array => [
                'key' => $field->value,
                'label' => $field->label(),
                'required' => $field->required(),
            ], ImportField::cases()),
            'mapping' => (object) ($import->mapping ?? []),
            'ticket_type_values' => array_values(array_map(fn (array $value): array => [
                ...$value,
                'ticket_type_id' => ($import->ticket_type_map ?? [])[RowParser::typeKey($value['value'])] ?? $byName->get(RowParser::typeKey($value['value']))?->id,
            ], $import->status->editable() ? $this->ticketTypeValues($import) : [])),
            'ticket_types' => $types->map(fn (TicketType $type): array => ['id' => $type->id, 'name' => $type->name])->values()->all(),
            'sample' => $sample,
            'sample_summary' => $summary,
        ];
    }
}
