<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Jobs\SendEmailDelivery;
use PHPeste\Notifications\Models\EmailDelivery;

class ResendEmail
{
    public function handle(EmailDelivery $delivery): EmailDelivery
    {
        if ($delivery->status === DeliveryStatus::Queued) {
            throw ValidationException::withMessages([
                'delivery' => 'Este envio ainda está na fila.',
            ]);
        }

        $delivery->update(['status' => DeliveryStatus::Queued, 'last_error' => null]);

        dispatch(new SendEmailDelivery($delivery->edition_id, $delivery->id));

        return $delivery->refresh();
    }
}
