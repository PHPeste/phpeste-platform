<?php

declare(strict_types=1);

namespace PHPeste\Payments\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Payments\Models\WebhookEventRecord;

class WebhookEventResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        if (!$event instanceof WebhookEventRecord) {
            return [];
        }

        return [
            'id' => $event->id,
            'payment_account_id' => $event->payment_account_id,
            'driver' => $event->driver,
            'event_id' => $event->event_id,
            'type' => $event->type,
            'gateway_charge_id' => $event->gateway_charge_id,
            'status' => $event->status->value,
            'status_label' => $event->status->label(),
            'result' => $event->result,
            'attempts' => $event->attempts,
            'can_reprocess' => $event->status->canReprocess(),
            'next_attempt_at' => $this->isoDate($event->next_attempt_at),
            'processed_at' => $this->isoDate($event->processed_at),
            'received_at' => $this->isoDate($event->created_at),
        ];
    }
}
