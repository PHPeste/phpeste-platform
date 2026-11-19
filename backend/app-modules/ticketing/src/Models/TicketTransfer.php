<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $ticket_id
 * @property string $from_name
 * @property string $from_email
 * @property string $to_name
 * @property string $to_email
 * @property string|null $transferred_by
 * @property string $channel
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'ticket_id', 'from_name', 'from_email', 'to_name', 'to_email', 'transferred_by', 'channel'])]
#[Table(name: 'ticket_transfers')]
class TicketTransfer extends Model
{
    use BelongsToEdition;
    use HasUuids;
}
