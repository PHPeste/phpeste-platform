<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;
use PHPeste\Sponsors\Enums\DeliverableStatus;

/**
 * Contrapartida a entregar ao patrocinador, com responsável e prazo.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $sponsorship_id
 * @property string $title
 * @property string|null $owner_id
 * @property Carbon|null $due_on
 * @property DeliverableStatus $status
 * @property Carbon|null $done_at
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Sponsorship $sponsorship
 */
#[Fillable(['edition_id', 'sponsorship_id', 'title', 'owner_id', 'due_on', 'status', 'done_at', 'position'])]
#[Table(name: 'sponsor_deliverables')]
class SponsorDeliverable extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return BelongsTo<Sponsorship, $this>
     */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DeliverableStatus::class,
            'due_on' => 'date',
            'done_at' => 'datetime',
            'position' => 'integer',
        ];
    }
}
