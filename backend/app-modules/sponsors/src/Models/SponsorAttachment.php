<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Sponsors\Enums\AttachmentKind;

/**
 * Contrato, logo vetorial ou outro arquivo, guardado no disco privado e
 * baixado só por URL assinada com expiração.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $sponsor_id
 * @property AttachmentKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'sponsor_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'])]
#[Hidden(['path'])]
#[Table(name: 'sponsor_attachments')]
class SponsorAttachment extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => AttachmentKind::class, 'size_bytes' => 'integer'];
    }
}
