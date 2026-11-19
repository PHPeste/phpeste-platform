<?php

declare(strict_types=1);

namespace PHPeste\Payments\Webhooks;

use Illuminate\Contracts\Database\Query\Builder;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Jobs\ProcessWebhookEvent;
use PHPeste\Payments\Models\WebhookEventRecord;

class RetryWebhooks
{
    public const int QUEUED_GRACE_MINUTES = 2;

    public function handle(): int
    {
        WebhookEventRecord::query()->withoutGlobalScopes()
            ->where('status', WebhookEventStatus::Processing->value)
            ->where('processing_started_at', '<=', now()->subMinutes(ProcessWebhook::STALE_MINUTES))
            ->update([
                'status' => WebhookEventStatus::Failed->value,
                'result' => 'Processamento interrompido antes de terminar.',
                'next_attempt_at' => now(),
                'updated_at' => now(),
            ]);

        $due = WebhookEventRecord::query()->withoutGlobalScopes()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $failed) => $failed->where('status', WebhookEventStatus::Failed->value)->where('next_attempt_at', '<=', now()))
                ->orWhere(fn (Builder $queued) => $queued->where('status', WebhookEventStatus::Received->value)->where('created_at', '<=', now()->subMinutes(self::QUEUED_GRACE_MINUTES))))
            ->oldest()
            ->limit(200)
            ->get(['id', 'edition_id']);

        foreach ($due as $event) {
            dispatch(new ProcessWebhookEvent($event->edition_id, $event->id));
        }

        return $due->count();
    }
}
