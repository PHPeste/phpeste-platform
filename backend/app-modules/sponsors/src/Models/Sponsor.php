<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use PHPeste\Community\Tenancy\BelongsToEdition;

/**
 * Empresa patrocinadora (ou em prospecção) nesta edição.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property string|null $website
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'name', 'website', 'contact_name', 'contact_email', 'contact_phone', 'notes'])]
#[Table(name: 'sponsors')]
class Sponsor extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return HasMany<Sponsorship, $this>
     */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class)->oldest()->orderBy('id');
    }

    /**
     * @return HasMany<SponsorContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SponsorContact::class)->latest('happened_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<SponsorAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SponsorAttachment::class)->latest()->orderByDesc('id');
    }
}
