<?php

declare(strict_types=1);

namespace PHPeste\Identity\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $accessibility_needs
 * @property string|null $dietary_restrictions
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['user_id', 'accessibility_needs', 'dietary_restrictions'])]
#[Table(name: 'speaker_private_details')]
class SpeakerPrivateDetails extends Model
{
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accessibility_needs' => 'encrypted',
            'dietary_restrictions' => 'encrypted',
        ];
    }
}
