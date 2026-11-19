<?php

declare(strict_types=1);

namespace PHPeste\Payments\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use PHPeste\Payments\Webhooks\ProcessWebhook;

class ProcessWebhookEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public readonly string $editionId,
        public readonly string $webhookEventId,
    ) {
        $this->afterCommit();
    }

    public function handle(ProcessWebhook $process): void
    {
        $process->handle($this->editionId, $this->webhookEventId);
    }
}
