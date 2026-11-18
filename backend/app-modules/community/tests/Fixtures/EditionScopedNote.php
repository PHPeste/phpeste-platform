<?php

declare(strict_types=1);

namespace PHPeste\Community\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $body
 */
#[Fillable(['edition_id', 'body'])]
#[Table(name: 'edition_scoped_notes')]
class EditionScopedNote extends Model
{
    use BelongsToEdition;
    use HasUuids;
}
