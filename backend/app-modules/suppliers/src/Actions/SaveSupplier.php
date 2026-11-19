<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;

/**
 * Cria o fornecedor no cadastro da comunidade já vinculado à edição, ou
 * edita os dados globais dele (valem para todas as edições da comunidade).
 */
class SaveSupplier
{
    public function __construct(
        private readonly LinkSupplier $link,
        private readonly EnsureTeamMember $team,
    ) {}

    /**
     * @param  array{name?: string, service?: string, description?: string|null, document?: string|null, website?: string|null, city?: string|null, state?: string|null}  $attributes
     * @param  list<array{name: string, role?: string|null, email?: string|null, phone?: string|null}>  $contacts
     */
    public function create(string $editionId, string $communityId, array $attributes, array $contacts, ?string $ownerId, ?string $actorId): EditionSupplier
    {
        $this->team->handle($editionId, $ownerId);
        $this->ensureUnique($communityId, $attributes, null);

        /** @var EditionSupplier */
        return DB::transaction(function () use ($editionId, $communityId, $attributes, $contacts, $ownerId, $actorId): EditionSupplier {
            $supplier = Supplier::create([
                ...$attributes,
                'community_id' => $communityId,
                'name_key' => Supplier::keyFor((string) ($attributes['name'] ?? '')),
            ]);

            foreach ($contacts as $contact) {
                $supplier->contacts()->create($contact);
            }

            return $this->link->handle($editionId, $supplier, $ownerId, $actorId);
        });
    }

    /**
     * @param  array{name?: string, service?: string, description?: string|null, document?: string|null, website?: string|null, city?: string|null, state?: string|null}  $attributes
     */
    public function update(Supplier $supplier, array $attributes): Supplier
    {
        $this->ensureUnique($supplier->community_id, $attributes, $supplier);

        $supplier->update([
            ...$attributes,
            ...(isset($attributes['name']) ? ['name_key' => Supplier::keyFor($attributes['name'])] : []),
        ]);

        return $supplier;
    }

    /**
     * @param  array{name?: string, document?: string|null}  $attributes
     */
    private function ensureUnique(string $communityId, array $attributes, ?Supplier $except): void
    {
        $existing = fn (string $column, string $value): ?Supplier => Supplier::query()
            ->where('community_id', $communityId)
            ->where($column, $value)
            ->when($except instanceof Supplier, fn ($query) => $query->whereKeyNot($except?->id))
            ->first();

        if (isset($attributes['name']) && ($found = $existing('name_key', Supplier::keyFor($attributes['name']))) instanceof Supplier) {
            throw ValidationException::withMessages(['name' => sprintf('%s já está no cadastro da comunidade. Use "reaproveitar fornecedor" para trazer para esta edição.', $found->name)]);
        }

        if (($attributes['document'] ?? null) !== null && ($found = $existing('document', (string) $attributes['document'])) instanceof Supplier) {
            throw ValidationException::withMessages(['document' => sprintf('Este CNPJ ou CPF já é de %s no cadastro da comunidade.', $found->name)]);
        }
    }
}
