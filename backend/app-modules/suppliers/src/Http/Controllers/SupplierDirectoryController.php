<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Suppliers\Access\SupplierAccess;
use PHPeste\Suppliers\Actions\EnsureTeamMember;
use PHPeste\Suppliers\Actions\LinkSupplier;
use PHPeste\Suppliers\Enums\SupplierService;
use PHPeste\Suppliers\Http\People;
use PHPeste\Suppliers\Http\Resources\SupplierPresenter;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;

/**
 * Busca no cadastro da comunidade para reaproveitar fornecedor de outra
 * edição. Mostra em que edições ele esteve e com que status; o contato só
 * aparece para quem é da equipe de uma dessas edições.
 */
class SupplierDirectoryController
{
    use Concerns;

    public function index(Request $request, CurrentEdition $edition, SupplierAccess $access, EditionDirectory $editions, SupplierPresenter $presenter): JsonResponse
    {
        $this->authorizeSuppliers();

        $editionId = (string) $edition->id();
        $search = mb_trim((string) $request->query('q'));
        $service = SupplierService::tryFrom((string) $request->query('service'));

        $suppliers = $access->suppliers($editionId)->with(['contacts', 'engagements'])
            ->when($search !== '', fn ($query) => $query->where('name_key', 'like', '%'.Supplier::keyFor($search).'%'))
            ->when($service !== null, fn ($query) => $query->where('service', $service?->value))
            ->orderBy('name')
            ->limit(50)
            ->get();
        $names = $editions->names(array_values(array_unique($suppliers->flatMap(fn (Supplier $supplier) => $supplier->engagements->pluck('edition_id'))->all())));
        $viewer = $this->actor($request);

        return response()->json(['data' => $suppliers->map(fn (Supplier $supplier): array => [
            ...$presenter->supplier($supplier, $viewer),
            'in_this_edition' => $supplier->engagements->contains('edition_id', $editionId),
            'editions' => $supplier->engagements
                ->filter(fn (EditionSupplier $link): bool => isset($names[$link->edition_id]))
                ->sortByDesc(fn (EditionSupplier $link): string => (string) $names[$link->edition_id]['starts_on'])
                ->map(fn (EditionSupplier $link): array => [
                    'id' => $link->edition_id,
                    'name' => $names[$link->edition_id]['name'],
                    'status' => $link->status->value,
                    'status_label' => $link->status->label(),
                ])->values()->all(),
        ])->all()]);
    }

    public function link(Request $request, CurrentEdition $edition, SupplierAccess $access, LinkSupplier $link, EnsureTeamMember $team, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $editionId = (string) $edition->id();
        $supplierId = $request->route('supplier');
        abort_unless(is_string($supplierId), 404);
        $owner = $request->input('owner_id');
        $owner = is_string($owner) && $owner !== '' ? $owner : null;

        $team->handle($editionId, $owner);

        $linked = $link->handle($editionId, $access->find($editionId, $supplierId), $owner, $this->actor($request));
        $linked->load(['supplier.contacts', 'quotes', 'attachments', 'activities']);

        $people->load([$linked->owner_id, ...$linked->activities->pluck('user_id')->all()]);

        return response()->json(['data' => $presenter->link($linked, $this->actor($request), detail: true)], 201);
    }
}
