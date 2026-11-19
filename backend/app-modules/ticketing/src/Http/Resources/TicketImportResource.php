<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Tickets\ImportedTickets;

class TicketImportResource extends ApiResource
{
    /**
     * @var array<string, mixed>|null
     */
    public ?array $preview = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $import = $this->resource;

        if (!$import instanceof TicketImport) {
            return [];
        }

        return [
            'id' => $import->id,
            'platform' => $import->platform,
            'file_name' => $import->file_name,
            'status' => $import->status->value,
            'status_label' => $import->status->label(),
            'failure' => $import->failure,
            'rows' => [
                'total' => $import->total_rows,
                'processed' => $import->processed_rows,
                'imported' => $import->imported_rows,
                'duplicates' => $import->duplicate_rows,
                'rejected' => $import->rejected_rows,
            ],
            'progress' => $import->total_rows === 0 ? 0 : (int) floor($import->processed_rows * 100 / $import->total_rows),
            'can_resume' => in_array($import->status, [ImportStatus::Failed, ImportStatus::Processing], true),
            'emails' => [
                'pending' => $import->status === ImportStatus::Completed ? resolve(ImportedTickets::class)->unsent($import)->count() : null,
                'requested_at' => $this->isoDate($import->emails_requested_at),
            ],
            'started_at' => $this->isoDate($import->started_at),
            'finished_at' => $this->isoDate($import->finished_at),
            'created_at' => $this->isoDate($import->created_at),
            ...($this->preview === null ? [] : ['preview' => $this->preview]),
        ];
    }
}
