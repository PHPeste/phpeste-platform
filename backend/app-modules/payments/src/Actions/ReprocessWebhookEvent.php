<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Events\WebhookEventReprocessed;
use PHPeste\Payments\Jobs\ProcessWebhookEvent;
use PHPeste\Payments\Models\WebhookEventRecord;

class ReprocessWebhookEvent
{
    public function handle(WebhookEventRecord $event, ?string $actorId): WebhookEventRecord
    {
        $previous = $event->status;

        $reset = $previous->canReprocess() ? WebhookEventRecord::query()->forEdition($event->edition_id)
            ->whereKey($event->id)
            ->where('status', $previous->value)
            ->update([
                'status' => WebhookEventStatus::Received->value,
                'attempts' => 0,
                'next_attempt_at' => null,
                'result' => null,
                'updated_at' => now(),
            ]) : 0;

        if ($reset !== 1) {
            throw ValidationException::withMessages(['status' => 'Só eventos com falha ou ignorados podem ser reprocessados.']);
        }

        event(new WebhookEventReprocessed($event->edition_id, $event->id, $previous->value, $actorId));
        dispatch(new ProcessWebhookEvent($event->edition_id, $event->id));

        return $event->refresh();
    }
}
