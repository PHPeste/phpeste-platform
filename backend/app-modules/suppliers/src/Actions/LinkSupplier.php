<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Events\SupplierStatusChanged;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;

/**
 * Coloca um fornecedor do cadastro da comunidade nesta edição, como "a
 * contatar". É o "reaproveitar fornecedor de edição anterior".
 */
class LinkSupplier
{
    public function __construct(private readonly RecordActivity $activity) {}

    public function handle(string $editionId, Supplier $supplier, ?string $ownerId, ?string $actorId): EditionSupplier
    {
        if (EditionSupplier::query()->forEdition($editionId)->where('supplier_id', $supplier->id)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => sprintf('%s já está nesta edição.', $supplier->name)]);
        }

        try {
            /** @var EditionSupplier */
            return DB::transaction(function () use ($editionId, $supplier, $ownerId, $actorId): EditionSupplier {
                $link = EditionSupplier::create([
                    'edition_id' => $editionId,
                    'supplier_id' => $supplier->id,
                    'status' => SupplierStatus::ToContact,
                    'owner_id' => $ownerId,
                ]);

                $this->activity->handle($link, ActivityKind::Status, 'Fornecedor adicionado à edição.', $actorId);
                event(new SupplierStatusChanged($editionId, $link->id, null, SupplierStatus::ToContact, null, $actorId));

                return $link;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['supplier_id' => sprintf('%s já está nesta edição.', $supplier->name)]);
        }
    }
}
