<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Access;

use Illuminate\Database\Eloquent\Builder;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;

/**
 * As duas regras de visibilidade do cadastro global:
 *
 * - fornecedor só existe dentro da comunidade da edição (outra comunidade
 *   recebe 404, nem sabe que ele existe);
 * - o contato do fornecedor só aparece para quem é da equipe de alguma
 *   edição em que ele foi vinculado.
 */
final class SupplierAccess
{
    /**
     * @var array<string, bool>
     */
    private array $contactCache = [];

    public function __construct(
        private readonly EditionDirectory $editions,
        private readonly EditionMemberships $memberships,
    ) {}

    public function communityOf(string $editionId): string
    {
        $community = $this->editions->communityId($editionId);
        abort_if($community === null, 404);

        return $community;
    }

    /**
     * @return Builder<Supplier>
     */
    public function suppliers(string $editionId): Builder
    {
        return Supplier::query()->where('community_id', $this->communityOf($editionId));
    }

    public function find(string $editionId, string $supplierId): Supplier
    {
        return $this->suppliers($editionId)->whereKey($supplierId)->firstOrFail();
    }

    public function canSeeContacts(?string $userId, Supplier $supplier): bool
    {
        if ($userId === null) {
            return false;
        }

        $key = $userId.'|'.$supplier->id;

        if (!array_key_exists($key, $this->contactCache)) {
            $editions = EditionSupplier::query()->withoutGlobalScopes()->where('supplier_id', $supplier->id)->pluck('edition_id')->all();
            $this->contactCache[$key] = collect($editions)->contains(fn (string $editionId): bool => $this->memberships->roleIn($editionId, $userId) instanceof EditionRole);
        }

        return $this->contactCache[$key];
    }
}
