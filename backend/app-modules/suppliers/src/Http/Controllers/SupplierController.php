<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Suppliers\Access\SupplierAccess;
use PHPeste\Suppliers\Actions\ChangeSupplierStatus;
use PHPeste\Suppliers\Actions\EnsureTeamMember;
use PHPeste\Suppliers\Actions\RecordActivity;
use PHPeste\Suppliers\Actions\SaveSupplier;
use PHPeste\Suppliers\Actions\UnlinkSupplier;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\SupplierService;
use PHPeste\Suppliers\Enums\SupplierStatus;
use PHPeste\Suppliers\Http\People;
use PHPeste\Suppliers\Http\Requests\ChangeStatusRequest;
use PHPeste\Suppliers\Http\Requests\LogActivityRequest;
use PHPeste\Suppliers\Http\Requests\SaveSupplierRequest;
use PHPeste\Suppliers\Http\Resources\SupplierPresenter;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Overview\SupplierTotals;

/**
 * Fornecedores desta edição. A lista não é paginada: os totais de orçado e
 * contratado vêm da mesma lista filtrada, para baterem com o que aparece.
 */
class SupplierController
{
    use Concerns;

    public function index(Request $request, SupplierPresenter $presenter, SupplierTotals $totals, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $status = SupplierStatus::tryFrom((string) $request->query('status'));
        $service = SupplierService::tryFrom((string) $request->query('service'));
        $search = mb_trim((string) $request->query('q'));

        $links = EditionSupplier::query()->with(['supplier.contacts', 'quotes'])
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($service !== null || $search !== '', fn ($query) => $query->whereHas('supplier', fn (Builder $supplier) => $supplier
                ->when($service !== null, fn ($inner) => $inner->where('service', $service?->value))
                ->when($search !== '', fn ($inner) => $inner->where('name', 'like', "%{$search}%"))))
            ->get()
            ->sortBy(fn (EditionSupplier $link): string => mb_strtolower($link->supplier->name))
            ->values();
        $people->load($links->pluck('owner_id')->all());
        $viewer = $this->actor($request);
        $summary = $totals->of($links);

        return response()->json([
            'data' => $links->map(fn (EditionSupplier $link): array => $presenter->link($link, $viewer))->all(),
            'totals' => [
                'count' => $summary['count'],
                'quoted' => SupplierPresenter::money($summary['quoted']),
                'contracted' => SupplierPresenter::money($summary['contracted']),
                'by_status' => $summary['by_status'],
            ],
        ]);
    }

    public function store(SaveSupplierRequest $request, CurrentEdition $edition, SupplierAccess $access, SaveSupplier $save, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $editionId = (string) $edition->id();
        $link = $save->create($editionId, $access->communityOf($editionId), $request->supplier(), $request->contacts(), $request->ownerId(), $this->actor($request));

        return response()->json(['data' => $this->detail($link, $request, $presenter, $people)], 201);
    }

    public function show(Request $request, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        return response()->json(['data' => $this->detail($this->link($request), $request, $presenter, $people)]);
    }

    /**
     * Edita os dados globais do fornecedor (valem para as outras edições da
     * comunidade) e o responsável nesta edição.
     */
    public function update(SaveSupplierRequest $request, SaveSupplier $save, EnsureTeamMember $team, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $link = $this->link($request);
        $save->update($link->supplier, $request->supplier());

        if ($request->has('owner_id')) {
            $team->handle($link->edition_id, $request->ownerId());
            $link->update(['owner_id' => $request->ownerId()]);
        }

        return response()->json(['data' => $this->detail($link, $request, $presenter, $people)]);
    }

    public function destroy(Request $request, UnlinkSupplier $unlink): Response
    {
        $this->authorizeSuppliers();

        $unlink->handle($this->link($request));

        return response()->noContent();
    }

    /**
     * Contratar (com orçamento aceito ou valor) e recusar (com motivo).
     */
    public function status(ChangeStatusRequest $request, ChangeSupplierStatus $change, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $link = $change->handle(
            $this->link($request),
            $request->status(),
            $request->filled('quote_id') ? $request->string('quote_id')->toString() : null,
            $request->filled('contracted_cents') ? $request->integer('contracted_cents') : null,
            $request->filled('reason') ? $request->string('reason')->toString() : null,
            $this->actor($request),
        );

        return response()->json(['data' => $this->detail($link, $request, $presenter, $people)]);
    }

    public function logActivity(LogActivityRequest $request, RecordActivity $record, SupplierPresenter $presenter, People $people): JsonResponse
    {
        $this->authorizeSuppliers();

        $link = $this->link($request);
        $record->handle(
            $link,
            ActivityKind::Note,
            $request->string('summary')->trim()->toString(),
            $this->actor($request),
            $request->channel(),
            $request->filled('happened_at') ? Date::parse($request->string('happened_at')->toString()) : null,
        );

        return response()->json(['data' => $this->detail($link, $request, $presenter, $people)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(EditionSupplier $link, Request $request, SupplierPresenter $presenter, People $people): array
    {
        $link->load(['supplier.contacts', 'quotes', 'attachments', 'activities']);
        $people->load([
            $link->owner_id,
            ...$link->quotes->pluck('recorded_by')->all(),
            ...$link->attachments->pluck('uploaded_by')->all(),
            ...$link->activities->pluck('user_id')->all(),
        ]);

        return $presenter->link($link, $this->actor($request), detail: true);
    }
}
