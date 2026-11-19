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
 * Cota de patrocínio: valor, vagas (nulo = sem limite) e contrapartidas.
 * Cada contrapartida vira uma entrega quando um acordo da cota fecha.
 *
 * @property string $id
 * @property string $edition_id
 * @property string $name
 * @property int $price_cents
 * @property int|null $slots
 * @property list<string> $benefits
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['edition_id', 'name', 'price_cents', 'slots', 'benefits', 'position'])]
#[Table(name: 'sponsor_tiers')]
class SponsorTier extends Model
{
    use BelongsToEdition;
    use HasUuids;

    /**
     * @return HasMany<Sponsorship, $this>
     */
    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'slots' => 'integer',
            'benefits' => 'array',
            'position' => 'integer',
        ];
    }
}
