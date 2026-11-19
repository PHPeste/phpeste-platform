<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPeste\Suppliers\Enums\SupplierService;

/**
 * Fornecedor da comunidade, global entre as edições dela. Não usa o escopo
 * de edição: toda consulta filtra por `community_id` (ver `Access\SupplierAccess`).
 *
 * @property string $id
 * @property string $community_id
 * @property string $name
 * @property string $name_key
 * @property SupplierService $service
 * @property string|null $description
 * @property string|null $document
 * @property string|null $website
 * @property string|null $city
 * @property string|null $state
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, SupplierContact> $contacts
 * @property-read Collection<int, EditionSupplier> $engagements
 */
#[Fillable(['community_id', 'name', 'name_key', 'service', 'description', 'document', 'website', 'city', 'state'])]
#[Table(name: 'suppliers')]
class Supplier extends Model
{
    use HasUuids;

    public static function keyFor(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower(Str::ascii(mb_trim($name))));
    }

    /**
     * @return HasMany<SupplierContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderBy('name')->orderBy('id');
    }

    /**
     * Vínculos com edições, sem o escopo da edição atual.
     *
     * @return HasMany<EditionSupplier, $this>
     */
    public function engagements(): HasMany
    {
        return $this->hasMany(EditionSupplier::class)->withoutGlobalScopes();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['service' => SupplierService::class];
    }
}
