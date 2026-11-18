<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $email
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'email'])]
#[Table(name: 'email_unsubscribes')]
class EmailUnsubscribe extends Model
{
    use BelongsToEdition;
    use HasUuids;
}
