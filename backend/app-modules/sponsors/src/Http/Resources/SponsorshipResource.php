<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;

class SponsorshipResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deal = $this->resource;

        if (!$deal instanceof Sponsorship) {
            return [];
        }

        $people = resolve(People::class);
        $received = $deal->relationLoaded('payments') ? (int) $deal->payments->sum('amount_cents') : (int) ($deal->getAttribute('received_cents') ?? 0);

        return [
            'id' => $deal->id,
            'sponsor' => $deal->relationLoaded('sponsor') ? ['id' => $deal->sponsor->id, 'name' => $deal->sponsor->name] : ['id' => $deal->sponsor_id],
            'tier' => $deal->relationLoaded('tier') && $deal->tier !== null ? ['id' => $deal->tier->id, 'name' => $deal->tier->name] : null,
            'stage' => $deal->stage->value,
            'stage_label' => $deal->stage->label(),
            'value' => $this->money($deal->value_cents),
            'received' => $this->money($received),
            'outstanding' => $this->money(max(0, $deal->value_cents - $received)),
            'owner' => $people->get($deal->owner_id),
            'lost_reason' => $deal->lost_reason,
            'position' => $deal->position,
            'closed_at' => $this->isoDate($deal->closed_at),
            'paid_at' => $this->isoDate($deal->paid_at),
            'lost_at' => $this->isoDate($deal->lost_at),
            'created_at' => $this->isoDate($deal->created_at),
            ...($deal->relationLoaded('payments') ? ['payments' => $deal->payments->map(fn (SponsorshipPayment $payment): array => [
                'id' => $payment->id,
                'amount' => $this->money($payment->amount_cents),
                'paid_on' => $payment->paid_on->toDateString(),
                'note' => $payment->note,
                'recorded_by' => $people->get($payment->recorded_by),
            ])->all()] : []),
            ...($deal->relationLoaded('deliverables') ? ['deliverables' => $deal->deliverables->map(fn (SponsorDeliverable $deliverable): array => SponsorDeliverableResource::present($deliverable, $people))->all()] : []),
        ];
    }
}
