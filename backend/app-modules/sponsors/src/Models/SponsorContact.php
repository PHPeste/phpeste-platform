<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Sponsors\Enums\ContactChannel;

/**
 * Registro do histórico de contatos com a empresa.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $sponsor_id
 * @property string|null $user_id
 * @property ContactChannel $channel
 * @property Carbon $happened_at
 * @property string $summary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'sponsor_id', 'user_id', 'channel', 'happened_at', 'summary'])]
#[Table(name: 'sponsor_contacts')]
class SponsorContact extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['channel' => ContactChannel::class, 'happened_at' => 'datetime'];
    }
}
