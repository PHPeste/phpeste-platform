<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Notifications\Enums\SuppressionReason;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $email
 * @property SuppressionReason $reason
 * @property string|null $detail
 * @property string|null $email_delivery_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'email', 'reason', 'detail', 'email_delivery_id'])]
#[Table(name: 'email_suppressions')]
class EmailSuppression extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reason' => SuppressionReason::class];
    }
}
