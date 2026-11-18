<?php

declare(strict_types=1);

namespace PHPeste\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Enums\BrazilianState;

/**
 * @property string $id
 * @property string $user_id
 * @property string $display_name
 * @property string|null $job_title
 * @property string|null $company
 * @property string|null $bio
 * @property string|null $city
 * @property BrazilianState|null $state
 * @property array<string, string|null> $links
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'display_name', 'job_title', 'company', 'bio', 'city', 'state', 'links'])]
class SpeakerProfile extends Model
{
    use HasUuids;

    public const array LINKS = ['website', 'github', 'linkedin'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => BrazilianState::class,
            'links' => 'array',
        ];
    }
}
