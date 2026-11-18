<?php

declare(strict_types=1);

namespace PHPeste\Community\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Database\Factories\EditionMemberFactory;
use PHPeste\Community\Enums\EditionRole;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $user_id
 * @property EditionRole $role
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Edition $edition
 */
#[Fillable(['edition_id', 'user_id', 'role'])]
class EditionMember extends Model
{
    /** @use HasFactory<EditionMemberFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => EditionRole::class,
        ];
    }
}
