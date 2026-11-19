<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Sponsors\Actions\SaveSponsorTier;
use PHPeste\Sponsors\Enums\SponsorshipStage;
use PHPeste\Sponsors\Http\Requests\SaveSponsorTierRequest;
use PHPeste\Sponsors\Http\Resources\SponsorTierResource;
use PHPeste\Sponsors\Models\SponsorTier;

class SponsorTierController
{
    use Concerns;

    public function index(): AnonymousResourceCollection
    {
        $this->authorizeSponsors();

        return SponsorTierResource::collection($this->query()->orderBy('position')->orderBy('name')->get());
    }

    public function store(SaveSponsorTierRequest $request, CurrentEdition $edition, SaveSponsorTier $save): JsonResponse
    {
        $this->authorizeSponsors();

        $tier = $save->handle((string) $edition->id(), null, $request->tier());

        return new SponsorTierResource($this->query()->findOrFail($tier->id))->response()->setStatusCode(201);
    }

    public function update(SaveSponsorTierRequest $request, CurrentEdition $edition, SaveSponsorTier $save): SponsorTierResource
    {
        $this->authorizeSponsors();

        $tier = $save->handle((string) $edition->id(), $this->find($request, 'tier', SponsorTier::class), $request->tier());

        return new SponsorTierResource($this->query()->findOrFail($tier->id));
    }

    public function destroy(Request $request, SaveSponsorTier $save): Response
    {
        $this->authorizeSponsors();

        $save->delete($this->find($request, 'tier', SponsorTier::class));

        return response()->noContent();
    }

    /**
     * @return Builder<SponsorTier>
     */
    private function query(): Builder
    {
        return SponsorTier::query()->withCount(['sponsorships as committed_count' => fn (Builder $query) => $query->whereIn('stage', SponsorshipStage::committedValues())]);
    }
}
