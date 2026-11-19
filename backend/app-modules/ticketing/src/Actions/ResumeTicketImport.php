<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Jobs\ProcessTicketImport;
use PHPeste\Ticketing\Models\TicketImport;

/**
 * Coloca de volta na fila uma importação interrompida ou parada. Ela
 * continua da posição gravada no último lote concluído.
 */
class ResumeTicketImport
{
    public function handle(TicketImport $import): TicketImport
    {
        if (!in_array($import->status, [ImportStatus::Failed, ImportStatus::Processing], true)) {
            throw ValidationException::withMessages(['import' => 'Só dá para retomar uma importação interrompida.']);
        }

        TicketImport::query()->whereKey($import->id)->whereIn('status', [ImportStatus::Failed->value, ImportStatus::Processing->value])
            ->update(['status' => ImportStatus::Processing->value, 'failure' => null, 'updated_at' => now()]);

        dispatch(new ProcessTicketImport($import->edition_id, $import->id));

        return $import->refresh();
    }
}
