<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use Illuminate\Support\Facades\Log;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Events\PaymentAccountVerified;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;
use PHPeste\Payments\Jobs\ProcessWebhookEvent;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\WebhookEventRecord;

class ReceiveWebhook
{
    public function __construct(private readonly GatewayRegistry $gateways) {}

    /**
     * @param  array<string, string>  $headers
     *
     * @throws InvalidWebhookSignature
     */
    public function handle(PaymentAccount $account, array $headers, string $body): WebhookEventRecord
    {
        try {
            $event = $this->gateways->driver($account->driver)->parseWebhook($account->gatewayCredentials(), $headers, $body);
        } catch (InvalidWebhookSignature $invalidWebhookSignature) {
            $this->reject($account, $invalidWebhookSignature);

            throw $invalidWebhookSignature;
        }

        $first = PaymentAccount::query()->forEdition($account->edition_id)
            ->whereKey($account->id)
            ->whereNull('webhook_verified_at')
            ->update(['webhook_verified_at' => now()]);

        if ($first === 1) {
            event(new PaymentAccountVerified($account->edition_id, $account->id, 'webhook', null));
        }

        $record = WebhookEventRecord::query()->forEdition($account->edition_id)->createOrFirst(
            ['payment_account_id' => $account->id, 'event_id' => mb_substr($event->eventId, 0, 191)],
            [
                'edition_id' => $account->edition_id,
                'driver' => $account->driver,
                'type' => mb_substr($event->type, 0, 100),
                'gateway_charge_id' => $event->chargeId,
                'status' => WebhookEventStatus::Received,
                'payload' => $body,
            ],
        );

        if ($record->wasRecentlyCreated) {
            dispatch(new ProcessWebhookEvent($record->edition_id, $record->id));
        }

        return $record;
    }

    private function reject(PaymentAccount $account, InvalidWebhookSignature $reason): void
    {
        PaymentAccount::query()->forEdition($account->edition_id)
            ->whereKey($account->id)
            ->increment('webhook_rejections', 1, ['webhook_rejected_at' => now()]);

        Log::warning('payments.webhook_invalid_signature', [
            'edition_id' => $account->edition_id,
            'account_id' => $account->id,
            'driver' => $account->driver,
            'reason' => $reason->getMessage(),
        ]);
    }
}
