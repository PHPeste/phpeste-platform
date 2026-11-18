<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Mail\EditionMail;
use PHPeste\Notifications\Mail\EmailComposer;
use PHPeste\Notifications\Models\EmailDelivery;
use Throwable;

class SendEmailDelivery implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public readonly string $editionId,
        public readonly string $deliveryId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1_800];
    }

    public function handle(EmailComposer $composer): void
    {
        $delivery = $this->delivery();

        if (!$delivery instanceof EmailDelivery || $delivery->status !== DeliveryStatus::Queued) {
            return;
        }

        $delivery->update(['attempts' => $delivery->attempts + 1]);

        try {
            $email = $composer->compose(
                $this->editionId,
                $delivery->template,
                $delivery->to_email,
                $delivery->variables,
                $delivery->action_url,
                $delivery->content,
            );

            $sent = Mail::to($delivery->to_email, $delivery->to_name)->send(new EditionMail($email));
        } catch (Throwable $throwable) {
            $delivery->update(['last_error' => mb_substr($throwable->getMessage(), 0, 2_000)]);

            throw $throwable;
        }

        $delivery->update([
            'status' => DeliveryStatus::Sent,
            'sent_at' => now(),
            'last_error' => null,
            'provider_message_id' => $sent?->getMessageId(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery()?->update([
            'status' => DeliveryStatus::Failed,
            'last_error' => $exception instanceof Throwable ? mb_substr($exception->getMessage(), 0, 2_000) : null,
        ]);
    }

    private function delivery(): ?EmailDelivery
    {
        return EmailDelivery::query()->forEdition($this->editionId)->find($this->deliveryId);
    }
}
