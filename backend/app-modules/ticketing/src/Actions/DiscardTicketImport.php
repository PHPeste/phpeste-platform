<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\TicketImport;

class DiscardTicketImport
{
    public function handle(TicketImport $import): void
    {
        if (!$import->status->editable()) {
            throw ValidationException::withMessages(['import' => 'Esta importação já começou e não pode ser descartada; os ingressos já emitidos continuam valendo.']);
        }

        if ($import->file_path !== null) {
            Storage::disk(ProcessImportChunk::disk())->delete($import->file_path);
        }

        $import->delete();
    }
}
