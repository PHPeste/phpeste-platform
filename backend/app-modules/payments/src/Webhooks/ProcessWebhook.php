<?php

declare(strict_types=1);

namespace PHPeste\Payments\Webhooks;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Models\WebhookEventRecord;
use Throwable;

class ProcessWebhook
{
    public const int MAX_ATTEMPTS = 5;

    public const array BACKOFF_MINUTES = [1, 5, 15, 60];

    public const int STALE_MINUTES = 10;

    public function __construct(private readonly PaymentProcessor $processor) {}

    public function handle(string $editionId, string $eventId): ?WebhookEventStatus
    {
        $claimed = WebhookEventRecord::query()->forEdition($editionId)
            ->whereKey($eventId)
            ->whereIn('status', [WebhookEventStatus::Received->value, WebhookEventStatus::Failed->value])
            ->where(fn (Builder $query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->update([
                'status' => WebhookEventStatus::Processing->value,
                'processing_started_at' => now(),
                'next_attempt_at' => null,
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return null;
        }

        $event = WebhookEventRecord::query()->forEdition($editionId)->findOrFail($eventId);

        try {
            [$status, $result] = $this->process($event);
        } catch (Throwable $throwable) {
            return $this->fail($event, $throwable);
        }

        $event->update(['status' => $status, 'result' => $result, 'processed_at' => now()]);

        return $status;
    }

    /**
     * @return array{WebhookEventStatus, string}
     */
    private function process(WebhookEventRecord $event): array
    {
        if ($event->gateway_charge_id === null) {
            return [WebhookEventStatus::Ignored, 'Evento sem cobrança associada.'];
        }

        $status = $this->processor->sync($event->edition_id, $event->payment_account_id, $event->gateway_charge_id);

        if (!$status instanceof ChargeStatus) {
            return [WebhookEventStatus::Ignored, 'Cobrança não encontrada na plataforma.'];
        }

        return [WebhookEventStatus::Processed, sprintf('Cobrança: %s.', mb_strtolower($status->label()))];
    }

    private function fail(WebhookEventRecord $event, Throwable $throwable): WebhookEventStatus
    {
        $retry = $event->attempts < self::MAX_ATTEMPTS;
        $wait = self::BACKOFF_MINUTES[min($event->attempts, count(self::BACKOFF_MINUTES)) - 1];

        $event->update([
            'status' => WebhookEventStatus::Failed,
            'result' => mb_substr($throwable->getMessage(), 0, 500),
            'next_attempt_at' => $retry ? now()->addMinutes($wait) : null,
        ]);

        Log::log($retry ? 'warning' : 'error', 'payments.webhook_failed', [
            'edition_id' => $event->edition_id,
            'webhook_event_id' => $event->id,
            'event_id' => $event->event_id,
            'attempts' => $event->attempts,
            'retry' => $retry,
            'error' => $throwable->getMessage(),
        ]);

        return WebhookEventStatus::Failed;
    }
}
