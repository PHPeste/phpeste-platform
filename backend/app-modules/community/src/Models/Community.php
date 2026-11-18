<?php

declare(strict_types=1);

namespace PHPeste\Community\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Database\Factories\CommunityFactory;
use PHPeste\Community\Enums\EditionRole;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'slug', 'created_by'])]
class Community extends Model
{
    /** @use HasFactory<CommunityFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return HasMany<Edition, $this>
     */
    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }

    public function isManagedBy(string $userId): bool
    {
        if ($this->created_by === $userId) {
            return true;
        }

        return EditionMember::query()
            ->where('user_id', $userId)
            ->where('role', EditionRole::Organizer)
            ->whereIn('edition_id', $this->editions()->select('id'))
            ->exists();
    }
}
