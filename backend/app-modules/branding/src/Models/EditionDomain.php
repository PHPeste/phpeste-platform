<?php

declare(strict_types=1);

namespace PHPeste\Branding\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $host
 * @property bool $is_platform
 * @property bool $is_primary
 * @property string $verification_token
 * @property Carbon|null $verified_at
 * @property Carbon|null $last_checked_at
 * @property string|null $last_check_error
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'host', 'is_platform', 'is_primary', 'verification_token', 'verified_at', 'last_checked_at', 'last_check_error'])]
#[Hidden(['verification_token'])]
#[Table(name: 'edition_domains')]
class EditionDomain extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function txtRecordName(): string
    {
        return "_phpeste-verify.{$this->host}";
    }

    public function txtRecordValue(): string
    {
        return "phpeste-verify={$this->verification_token}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_platform' => 'boolean',
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }
}
