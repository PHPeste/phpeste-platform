<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Events\TicketImportStarted;
use PHPeste\Ticketing\Imports\ImportPreview;
use PHPeste\Ticketing\Jobs\ProcessTicketImport;
use PHPeste\Ticketing\Models\TicketImport;

class StartTicketImport
{
    public function handle(TicketImport $import, ?string $actorId): TicketImport
    {
        if ($import->status !== ImportStatus::Ready) {
            throw ValidationException::withMessages(['import' => $import->status === ImportStatus::Uploaded
                ? 'Confirme o mapeamento das colunas antes de importar.'
                : 'Esta importação já começou.']);
        }

        $offset = ImportPreview::file($import)?->dataOffset() ?? 0;

        DB::transaction(function () use ($import, $offset, $actorId): void {
            $started = TicketImport::query()->whereKey($import->id)->where('status', ImportStatus::Ready->value)->update([
                'status' => ImportStatus::Processing->value,
                'started_at' => now(),
                'byte_offset' => $offset,
                'processed_rows' => 0,
                'updated_at' => now(),
            ]);

            if ($started === 0) {
                throw ValidationException::withMessages(['import' => 'Esta importação já começou.']);
            }

            event(new TicketImportStarted($import->edition_id, $import->id, $import->platform, $import->total_rows, $actorId));
            dispatch(new ProcessTicketImport($import->edition_id, $import->id));
        });

        return $import->refresh();
    }
}
