<?php

declare(strict_types=1);

namespace PHPeste\Community\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Database\Factories\EditionInvitationFactory;
use PHPeste\Community\Enums\EditionRole;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $email
 * @property string|null $name
 * @property EditionRole $role
 * @property string $token_hash
 * @property string|null $invited_by
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property string|null $accepted_by
 * @property Carbon|null $revoked_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Edition $edition
 */
#[Fillable(['edition_id', 'email', 'name', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'accepted_by', 'revoked_at'])]
#[Hidden(['token_hash'])]
class EditionInvitation extends Model
{
    /** @use HasFactory<EditionInvitationFactory> */
    use HasFactory;

    use HasUuids;

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /**
     * @param  Builder<static>  $query
     */
    protected function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => EditionRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
