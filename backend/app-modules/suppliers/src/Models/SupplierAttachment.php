<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Suppliers\Enums\AttachmentKind;

/**
 * @property string $id
 * @property string $edition_id
 * @property string $edition_supplier_id
 * @property AttachmentKind $kind
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $uploaded_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'edition_supplier_id', 'kind', 'path', 'original_name', 'mime_type', 'size_bytes', 'uploaded_by'])]
#[Hidden(['path'])]
#[Table(name: 'supplier_attachments')]
class SupplierAttachment extends Model
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
