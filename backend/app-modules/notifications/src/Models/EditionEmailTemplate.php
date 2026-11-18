<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Notifications\Enums\EmailTemplate;

/**
 * @property string $id
 * @property string $edition_id
 * @property EmailTemplate $template
 * @property string $subject
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'template', 'subject', 'body'])]
#[Table(name: 'email_templates')]
class EditionEmailTemplate extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => EmailTemplate::class,
        ];
    }
}
