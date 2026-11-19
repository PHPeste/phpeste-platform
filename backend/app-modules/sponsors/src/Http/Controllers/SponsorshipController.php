<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use PHPeste\Sponsors\Actions\DeleteSponsorship;
use PHPeste\Sponsors\Actions\MoveSponsorship;
use PHPeste\Sponsors\Actions\RecordSponsorshipPayment;
use PHPeste\Sponsors\Actions\SaveSponsorship;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Http\Requests\MoveSponsorshipRequest;
use PHPeste\Sponsors\Http\Requests\RecordPaymentRequest;
use PHPeste\Sponsors\Http\Requests\SaveSponsorshipRequest;
use PHPeste\Sponsors\Http\Resources\SponsorshipResource;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

class SponsorshipController
{
    use Concerns;

    public function store(SaveSponsorshipRequest $request, SaveSponsorship $save, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $deal = $save->handle($this->find($request, 'sponsor', Sponsor::class), null, $request->sponsorship(), $request->boolean('confirm'), $this->actor($request));

        return $this->present($deal, $people)->response()->setStatusCode(201);
    }

    public function show(Request $request, People $people): SponsorshipResource
    {
        $this->authorizeSponsors();

        return $this->present($this->deal($request), $people);
    }

    public function update(SaveSponsorshipRequest $request, SaveSponsorship $save, People $people): SponsorshipResource
    {
        $this->authorizeSponsors();

        $deal = $this->deal($request);
        $save->handle($deal->sponsor, $deal, $request->sponsorship(), $request->boolean('confirm'), $this->actor($request));

        return $this->present($deal, $people);
    }

    public function move(MoveSponsorshipRequest $request, MoveSponsorship $move, People $people): SponsorshipResource
    {
        $this->authorizeSponsors();

        $deal = $move->handle(
            $this->deal($request),
            $request->stage(),
            $request->filled('position') ? $request->integer('position') : null,
            $request->filled('lost_reason') ? $request->string('lost_reason')->toString() : null,
            $request->boolean('confirm'),
            $this->actor($request),
        );

        return $this->present($deal, $people);
    }

    public function destroy(Request $request, DeleteSponsorship $delete): Response
    {
        $this->authorizeSponsors();

        $delete->handle($this->deal($request));

        return response()->noContent();
    }

    public function recordPayment(RecordPaymentRequest $request, RecordSponsorshipPayment $payments, People $people): JsonResponse
    {
        $this->authorizeSponsors();

        $deal = $this->deal($request);
        $payments->record(
            $deal,
            $request->integer('amount_cents'),
            Date::createFromFormat('Y-m-d', $request->string('paid_on')->toString())?->startOfDay() ?? today(),
            $request->filled('note') ? $request->string('note')->toString() : null,
            $this->actor($request),
        );

        return $this->present($deal->refresh(), $people)->response()->setStatusCode(201);
    }

    public function deletePayment(Request $request, RecordSponsorshipPayment $payments): Response
    {
        $this->authorizeSponsors();

        $deal = $this->deal($request);
        $payment = $this->find($request, 'payment', SponsorshipPayment::class);
        abort_unless($payment->sponsorship_id === $deal->id, 404);
        $payments->remove($deal, $payment, $this->actor($request));

        return response()->noContent();
    }

    private function deal(Request $request): Sponsorship
    {
        return $this->find($request, 'sponsorship', Sponsorship::class);
    }

    private function present(Sponsorship $deal, People $people): SponsorshipResource
    {
        $deal->load(['sponsor:id,name', 'tier:id,name', 'payments', 'deliverables']);
        $people->load([$deal->owner_id, ...$deal->payments->pluck('recorded_by')->all(), ...$deal->deliverables->pluck('owner_id')->all()]);

        return new SponsorshipResource($deal);
    }
}
