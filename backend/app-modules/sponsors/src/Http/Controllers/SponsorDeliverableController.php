<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use PHPeste\Sponsors\Actions\SaveDeliverable;
use PHPeste\Sponsors\Enums\DeliverableStatus;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Http\Requests\SaveDeliverableRequest;
use PHPeste\Sponsors\Http\Resources\SponsorDeliverableResource;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\Sponsorship;

class SponsorDeliverableController
{
    use Concerns;

    /**
     * Todas as entregas da edição, para a lista "o que falta entregar".
     * Filtros: `status`, `owner_id`, `overdue=1`.
     */
    public function index(Request $request, People $people): AnonymousResourceCollection
    {
        $this->authorizeSponsors();

        $status = DeliverableStatus::tryFrom((string) $request->query('status'));
        $owner = $request->query('owner_id');

        $deliverables = SponsorDeliverable::query()->with('sponsorship.sponsor:id,name')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when(is_string($owner) && $owner !== '', fn ($query) => $query->where('owner_id', $owner))
            ->when($request->boolean('overdue'), fn ($query) => $query->where('status', '!=', DeliverableStatus::Done->value)->whereDate('due_on', '<', today()->toDateString()))
            ->orderByRaw('due_on is null')->oldest('due_on')->orderBy('id')
            ->paginate(50);
        $people->load(collect($deliverables->items())->pluck('owner_id')->all());

        return SponsorDeliverableResource::collection($deliverables);
    }

    public function store(SaveDeliverableRequest $request, SaveDeliverable $save, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $deliverable = $save->handle($this->find($request, 'sponsorship', Sponsorship::class), null, $request->deliverable());
        $people->load([$deliverable->owner_id]);

        return new SponsorDeliverableResource($deliverable)->response()->setStatusCode(201);
    }

    public function update(SaveDeliverableRequest $request, SaveDeliverable $save, People $people): SponsorDeliverableResource
    {
        $this->authorizeSponsors();

        $deliverable = $this->find($request, 'deliverable', SponsorDeliverable::class);
        $save->handle($deliverable->sponsorship, $deliverable, $request->deliverable());
        $people->load([$deliverable->owner_id]);

        return new SponsorDeliverableResource($deliverable);
    }

    public function destroy(Request $request): Response
    {
        $this->authorizeSponsors();

        $this->find($request, 'deliverable', SponsorDeliverable::class)->delete();

        return response()->noContent();
    }
}
