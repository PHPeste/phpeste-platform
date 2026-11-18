<?php

declare(strict_types=1);

namespace PHPeste\Payments\Actions;

use PHPeste\Payments\Data\WebhookEvent;
use PHPeste\Payments\Events\PaymentAccountVerified;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;
use PHPeste\Payments\Models\PaymentAccount;

class ReceiveWebhookPing
{
    public function __construct(private readonly GatewayRegistry $gateways) {}

    /**
     * @param  array<string, string>  $headers
     *
     * @throws InvalidWebhookSignature
     */
    public function handle(PaymentAccount $account, array $headers, string $body): WebhookEvent
    {
        $event = $this->gateways->driver($account->driver)->parseWebhook($account->gatewayCredentials(), $headers, $body);

        $first = PaymentAccount::query()->forEdition($account->edition_id)
            ->whereKey($account->id)
            ->whereNull('webhook_verified_at')
            ->update(['webhook_verified_at' => now()]);

        if ($first === 1) {
            event(new PaymentAccountVerified($account->edition_id, $account->id, 'webhook', null));
        }

        return $event;
    }
}
