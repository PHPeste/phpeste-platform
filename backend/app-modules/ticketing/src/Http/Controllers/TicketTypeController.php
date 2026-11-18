<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\DeleteTicketBatch;
use PHPeste\Ticketing\Actions\DeleteTicketType;
use PHPeste\Ticketing\Actions\SaveTicketBatch;
use PHPeste\Ticketing\Actions\SaveTicketType;
use PHPeste\Ticketing\Actions\UpdateTicketingSettings;
use PHPeste\Ticketing\Http\Requests\SaveTicketBatchRequest;
use PHPeste\Ticketing\Http\Requests\SaveTicketTypeRequest;
use PHPeste\Ticketing\Http\Requests\UpdateTicketingSettingsRequest;
use PHPeste\Ticketing\Http\Resources\TicketBatchResource;
use PHPeste\Ticketing\Http\Resources\TicketTypeResource;
use PHPeste\Ticketing\Inventory\BatchRotation;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

class TicketTypeController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('ticketing.view');

        $editionId = $this->editionId();
        $types = TicketType::query()->with('batches')->orderBy('position')->orderBy('name')->get();
        $settings = TicketingSetting::query()->firstOrCreate(['edition_id' => $editionId]);

        return response()->json([
            'data' => TicketTypeResource::collection($types)->resolve($request),
            'meta' => $this->settings($settings),
        ]);
    }

    public function updateSettings(UpdateTicketingSettingsRequest $request, UpdateTicketingSettings $update): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        return response()->json(['data' => $this->settings($update->handle($this->editionId(), $attributes))]);
    }

    public function store(SaveTicketTypeRequest $request, SaveTicketType $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $type = $save->handle($this->editionId(), null, $request->ticketType());

        return new TicketTypeResource($type->load('batches'))->response()->setStatusCode(201);
    }

    public function update(SaveTicketTypeRequest $request, SaveTicketType $save): TicketTypeResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new TicketTypeResource($save->handle($this->editionId(), $this->type($request), $request->ticketType())->load('batches'));
    }

    public function destroy(Request $request, DeleteTicketType $delete): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $delete->handle($this->type($request));

        return response()->noContent();
    }

    public function storeBatch(SaveTicketBatchRequest $request, SaveTicketBatch $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();

        return new TicketBatchResource($save->handle($this->type($request), null, $attributes))->response()->setStatusCode(201);
    }

    public function updateBatch(SaveTicketBatchRequest $request, SaveTicketBatch $save): TicketBatchResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        /** @var array<string, mixed> $attributes */
        $attributes = $request->validated();
        $type = $this->type($request);

        return new TicketBatchResource($save->handle($type, $this->batch($request, $type), $attributes));
    }

    public function closeBatch(Request $request, BatchRotation $rotation): TicketBatchResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $batch = $this->batch($request, $this->type($request));
        $rotation->close($batch);

        return new TicketBatchResource($batch->fresh() ?? $batch);
    }

    public function destroyBatch(Request $request, DeleteTicketBatch $delete): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $delete->handle($this->batch($request, $this->type($request)));

        return response()->noContent();
    }

    /**
     * @return array<string, int|null>
     */
    private function settings(TicketingSetting $settings): array
    {
        return [
            'capacity' => $settings->capacity,
            'allocated' => $settings->allocated,
            'remaining' => $settings->capacity === null ? null : max(0, $settings->capacity - $settings->allocated),
            'reservation_minutes' => $settings->reservation_minutes,
            'max_tickets_per_buyer' => $settings->max_tickets_per_buyer,
            'gateway_fee_basis_points' => $settings->gateway_fee_basis_points,
        ];
    }

    private function editionId(): string
    {
        return (string) $this->currentEdition->id();
    }

    private function type(Request $request): TicketType
    {
        $id = $request->route('ticketType');
        abort_unless(is_string($id), 404);

        return TicketType::query()->with('batches')->whereKey($id)->firstOrFail();
    }

    private function batch(Request $request, TicketType $type): TicketBatch
    {
        $id = $request->route('batch');
        abort_unless(is_string($id), 404);

        return TicketBatch::query()->where('ticket_type_id', $type->id)->whereKey($id)->firstOrFail();
    }
}
