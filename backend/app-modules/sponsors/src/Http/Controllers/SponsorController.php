<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Sponsors\Actions\LogSponsorContact;
use PHPeste\Sponsors\Actions\SaveSponsor;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Http\Requests\LogContactRequest;
use PHPeste\Sponsors\Http\Requests\SaveSponsorRequest;
use PHPeste\Sponsors\Http\Resources\SponsorResource;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorContact;
use PHPeste\Sponsors\Models\Sponsorship;

class SponsorController
{
    use Concerns;

    public function index(Request $request, People $people): AnonymousResourceCollection
    {
        $this->authorizeSponsors();

        $search = mb_trim((string) $request->query('q'));
        $sponsors = Sponsor::query()
            ->with(['sponsorships' => fn ($query) => $query->with('tier:id,name')->withSum('payments as received_cents', 'amount_cents')])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(50);
        $people->load(collect($sponsors->items())->flatMap(fn (Sponsor $sponsor) => $sponsor->sponsorships->pluck('owner_id'))->all());

        return SponsorResource::collection($sponsors);
    }

    public function store(SaveSponsorRequest $request, CurrentEdition $edition, SaveSponsor $save): JsonResponse
    {
        $this->authorizeSponsors();

        return new SponsorResource($save->handle((string) $edition->id(), null, $request->sponsor()))->response()->setStatusCode(201);
    }

    public function show(Request $request, People $people): SponsorResource
    {
        $this->authorizeSponsors();

        return new SponsorResource($this->detail($this->find($request, 'sponsor', Sponsor::class), $people));
    }

    public function update(SaveSponsorRequest $request, CurrentEdition $edition, SaveSponsor $save, People $people): SponsorResource
    {
        $this->authorizeSponsors();

        return new SponsorResource($this->detail($save->handle((string) $edition->id(), $this->find($request, 'sponsor', Sponsor::class), $request->sponsor()), $people));
    }

    public function destroy(Request $request, SaveSponsor $save): Response
    {
        $this->authorizeSponsors();

        $save->delete($this->find($request, 'sponsor', Sponsor::class));

        return response()->noContent();
    }

    public function logContact(LogContactRequest $request, LogSponsorContact $log, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $sponsor = $this->find($request, 'sponsor', Sponsor::class);
        $happenedAt = $request->filled('happened_at') ? Date::parse($request->string('happened_at')->toString()) : now();
        $log->handle($sponsor, $request->channel(), $happenedAt, $request->string('summary')->trim()->toString(), $this->actor($request));

        return new SponsorResource($this->detail($sponsor, $people))->response()->setStatusCode(201);
    }

    public function deleteContact(Request $request): Response
    {
        $this->authorizeSponsors();

        $sponsor = $this->find($request, 'sponsor', Sponsor::class);
        $contact = $this->find($request, 'contact', SponsorContact::class);
        abort_unless($contact->sponsor_id === $sponsor->id, 404);
        $contact->delete();

        return response()->noContent();
    }

    private function detail(Sponsor $sponsor, People $people): Sponsor
    {
        $sponsor->load(['sponsorships' => fn ($query) => $query->with(['tier:id,name', 'payments', 'deliverables']), 'contacts', 'attachments']);
        $people->load([
            ...$sponsor->sponsorships->pluck('owner_id')->all(),
            ...$sponsor->sponsorships->flatMap(fn (Sponsorship $deal): array => [...$deal->payments->pluck('recorded_by'), ...$deal->deliverables->pluck('owner_id')])->all(),
            ...$sponsor->contacts->pluck('user_id')->all(),
            ...$sponsor->attachments->pluck('uploaded_by')->all(),
        ]);

        return $sponsor;
    }
}
