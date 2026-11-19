<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Ticketing\Enums\ImportRowOutcome;

/**
 * Linha do CSV que não virou ingresso: rejeitada (com o motivo) ou repetida.
 * Os valores da linha ficam cifrados, porque têm nome e e-mail.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $ticket_import_id
 * @property int $line
 * @property ImportRowOutcome $kind
 * @property string $reason
 * @property list<string> $data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'ticket_import_id', 'line', 'kind', 'reason', 'data'])]
#[Table(name: 'ticket_import_rejections')]
class TicketImportRejection extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ImportRowOutcome::class,
            'line' => 'integer',
            'data' => 'encrypted:array',
        ];
    }
}
