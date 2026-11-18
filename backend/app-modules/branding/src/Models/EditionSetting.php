<?php

declare(strict_types=1);

namespace PHPeste\Branding\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Branding\Theme\ThemeTokens;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @phpstan-import-type Theme from ThemeTokens
 *
 * @property string $id
 * @property string $edition_id
 * @property Theme $theme_draft
 * @property Theme|null $theme_published
 * @property Theme|null $theme_previous
 * @property Carbon|null $theme_published_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'theme_draft', 'theme_published', 'theme_previous', 'theme_published_at'])]
#[Table(name: 'edition_settings')]
class EditionSetting extends Model
{
    use BelongsToEdition;
    use HasUuids;

    public function hasUnpublishedChanges(): bool
    {
        return $this->theme_draft !== $this->theme_published;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'theme_draft' => 'array',
            'theme_published' => 'array',
            'theme_previous' => 'array',
            'theme_published_at' => 'datetime',
        ];
    }
}
