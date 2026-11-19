<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\ImportField;
use PHPeste\Ticketing\Enums\ImportStatus;

/**
 * @property string $id
 * @property string $edition_id
 * @property string|null $created_by
 * @property string $platform
 * @property ImportStatus $status
 * @property string $file_name
 * @property string|null $file_path
 * @property string $delimiter
 * @property string $encoding
 * @property list<string> $headers
 * @property array<string, int>|null $mapping
 * @property array<string, string|null>|null $ticket_type_map
 * @property string|null $default_ticket_type_id
 * @property int $total_rows
 * @property int $processed_rows
 * @property int $byte_offset
 * @property int $imported_rows
 * @property int $duplicate_rows
 * @property int $rejected_rows
 * @property string|null $failure
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $emails_requested_at
 * @property string|null $emails_requested_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'created_by', 'platform', 'status', 'file_name', 'file_path', 'delimiter', 'encoding', 'headers', 'mapping', 'ticket_type_map', 'default_ticket_type_id', 'total_rows', 'processed_rows', 'byte_offset', 'imported_rows', 'duplicate_rows', 'rejected_rows', 'failure', 'started_at', 'finished_at', 'emails_requested_at', 'emails_requested_by'])]
#[Hidden(['file_path'])]
#[Table(name: 'ticket_imports')]
class TicketImport extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return HasMany<TicketImportRejection, $this>
     */
    public function rejections(): HasMany
    {
        return $this->hasMany(TicketImportRejection::class)->orderBy('line');
    }

    public function column(ImportField $field): ?int
    {
        $index = $this->mapping[$field->value] ?? null;

        return is_int($index) ? $index : null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'headers' => 'array',
            'mapping' => 'array',
            'ticket_type_map' => 'array',
            'total_rows' => 'integer',
            'processed_rows' => 'integer',
            'byte_offset' => 'integer',
            'imported_rows' => 'integer',
            'duplicate_rows' => 'integer',
            'rejected_rows' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'emails_requested_at' => 'datetime',
        ];
    }
}
