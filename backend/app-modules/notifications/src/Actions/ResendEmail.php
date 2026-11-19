<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Jobs\SendEmailDelivery;
use PHPeste\Notifications\Models\EmailDelivery;

class ResendEmail
{
    public function __construct(private readonly QueueEmail $queue) {}

    public function handle(EmailDelivery $delivery): EmailDelivery
    {
        if ($this->queue->isBlocked($delivery->edition_id, $delivery->to_email)) {
            throw ValidationException::withMessages([
                'delivery' => 'Este endereço está bloqueado por bounce ou spam. Libere em "Endereços bloqueados" antes de reenviar.',
            ]);
        }

        if ($delivery->status === DeliveryStatus::Queued) {
            throw ValidationException::withMessages([
                'delivery' => 'Este envio ainda está na fila.',
            ]);
        }

        $delivery->update(['status' => DeliveryStatus::Queued, 'last_error' => null, 'provider_detail' => null, 'delivered_at' => null, 'bounced_at' => null, 'complained_at' => null]);

        dispatch(new SendEmailDelivery($delivery->edition_id, $delivery->id));

        return $delivery->refresh();
    }
}
