<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Enums\ImportRowOutcome;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Events\TicketImportFinished;
use PHPeste\Ticketing\Imports\CsvFile;
use PHPeste\Ticketing\Imports\ImportedOrders;
use PHPeste\Ticketing\Imports\ImportRow;
use PHPeste\Ticketing\Imports\RowEvaluator;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketImportRejection;
use PHPeste\Ticketing\Models\TicketType;
use RuntimeException;

/**
 * Importa o próximo lote de linhas de uma importação em andamento.
 *
 * O lote inteiro (pedidos, ingressos, rejeições e a posição no arquivo)
 * é gravado numa transação só: se o processo cair no meio, nada do lote
 * fica pela metade e a próxima tentativa recomeça da mesma linha.
 */
class ProcessImportChunk
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly RowEvaluator $evaluator,
        private readonly ImportedOrders $orders,
    ) {}

    public static function disk(): string
    {
        return (string) config('ticketing.imports.disk', 'local');
    }

    /**
     * @return bool `true` quando a importação terminou
     */
    public function handle(string $editionId, string $importId): bool
    {
        /** @var bool */
        return $this->currentEdition->run($editionId, function () use ($importId): bool {
            $import = TicketImport::query()->find($importId);

            if (!$import instanceof TicketImport || $import->status !== ImportStatus::Processing) {
                return true;
            }

            $done = DB::transaction(fn (): bool => $this->chunk($import));

            if ($done) {
                $this->finish($import->refresh());
            }

            return $done;
        });
    }

    private function chunk(TicketImport $import): bool
    {
        $file = CsvFile::open(Storage::disk(self::disk())->path((string) $import->file_path), $import->delimiter, $import->encoding);
        $types = TicketType::query()->get()->keyBy('id');
        $size = max(1, (int) config('ticketing.imports.chunk_size', 200));
        $counts = ['processed_rows' => 0, 'imported_rows' => 0, 'duplicate_rows' => 0, 'rejected_rows' => 0];
        $offset = $import->byte_offset;
        $line = $import->processed_rows + 1;
        $read = 0;

        foreach ($file->records($import->byte_offset) as $record) {
            $offset = $record['next'];

            if ($record['blank']) {
                continue;
            }

            $line = $import->processed_rows + $counts['processed_rows'] + 1;
            $counts['processed_rows']++;
            $evaluation = $this->evaluator->evaluate($import, $record['values']);
            $reason = $evaluation->reason;
            $kind = $evaluation->outcome;

            if ($kind === ImportRowOutcome::Import && $evaluation->row instanceof ImportRow) {
                $type = $types->get($evaluation->row->ticketTypeId);
                $reason = $type instanceof TicketType
                    ? $this->orders->write($import, $evaluation->row, $type, $line)
                    : 'O tipo de ingresso escolhido foi removido.';
                $kind = $reason === null ? ImportRowOutcome::Import : ImportRowOutcome::Rejected;
            }

            match ($kind) {
                ImportRowOutcome::Import => $counts['imported_rows']++,
                ImportRowOutcome::Duplicate => $counts['duplicate_rows']++,
                ImportRowOutcome::Rejected => $counts['rejected_rows']++,
            };

            if ($kind !== ImportRowOutcome::Import) {
                TicketImportRejection::create([
                    'edition_id' => $import->edition_id,
                    'ticket_import_id' => $import->id,
                    'line' => $line,
                    'kind' => $kind,
                    'reason' => (string) $reason,
                    'data' => $record['values'],
                ]);
            }

            if (++$read >= $size) {
                break;
            }
        }

        $done = $read < $size;

        $moved = TicketImport::query()->whereKey($import->id)->where('byte_offset', $import->byte_offset)->update([
            'byte_offset' => $offset,
            'processed_rows' => $import->processed_rows + $counts['processed_rows'],
            'imported_rows' => $import->imported_rows + $counts['imported_rows'],
            'duplicate_rows' => $import->duplicate_rows + $counts['duplicate_rows'],
            'rejected_rows' => $import->rejected_rows + $counts['rejected_rows'],
            'updated_at' => now(),
        ]);

        throw_if($moved === 0, RuntimeException::class, 'Outra execução já importou este lote.');

        return $done;
    }

    private function finish(TicketImport $import): void
    {
        if ($import->file_path !== null) {
            Storage::disk(self::disk())->delete($import->file_path);
        }

        $import->update(['status' => ImportStatus::Completed, 'finished_at' => now(), 'file_path' => null, 'failure' => null]);

        event(new TicketImportFinished($import->edition_id, $import->id, $import->imported_rows, $import->duplicate_rows, $import->rejected_rows));
    }
}
