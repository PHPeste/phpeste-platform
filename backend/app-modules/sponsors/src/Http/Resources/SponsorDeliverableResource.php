<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Sponsors\Enums\DeliverableStatus;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Models\SponsorDeliverable;

class SponsorDeliverableResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public static function present(SponsorDeliverable $deliverable, People $people): array
    {
        return [
            'id' => $deliverable->id,
            'sponsorship_id' => $deliverable->sponsorship_id,
            'title' => $deliverable->title,
            'owner' => $people->get($deliverable->owner_id),
            'due_on' => $deliverable->due_on?->toDateString(),
            'status' => $deliverable->status->value,
            'status_label' => $deliverable->status->label(),
            'overdue' => $deliverable->status !== DeliverableStatus::Done && $deliverable->due_on !== null && $deliverable->due_on->lt(today()),
            'done_at' => $deliverable->done_at?->clone()->utc()->toIso8601ZuluString(),
            'position' => $deliverable->position,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deliverable = $this->resource;

        if (!$deliverable instanceof SponsorDeliverable) {
            return [];
        }

        $sponsorship = $deliverable->relationLoaded('sponsorship') ? $deliverable->sponsorship : null;

        return [
            ...self::present($deliverable, resolve(People::class)),
            ...($sponsorship !== null && $sponsorship->relationLoaded('sponsor') ? ['sponsor' => ['id' => $sponsorship->sponsor->id, 'name' => $sponsorship->sponsor->name]] : []),
        ];
    }
}
