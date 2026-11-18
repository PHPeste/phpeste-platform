<?php

declare(strict_types=1);

namespace PHPeste\Payments\Contracts;

use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\ChargeSnapshot;
use PHPeste\Payments\Data\GatewayCredentials;
use PHPeste\Payments\Data\HostedCheckout;
use PHPeste\Payments\Data\PixCharge;
use PHPeste\Payments\Data\RefundResult;
use PHPeste\Payments\Data\VerificationResult;
use PHPeste\Payments\Data\WebhookEvent;
use PHPeste\Payments\Enums\GatewayCapability;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Gateway\InvalidWebhookSignature;

interface PaymentGateway
{
    public function key(): string;

    public function label(): string;

    /**
     * @return list<GatewayCapability>
     */
    public function capabilities(): array;

    public function verify(GatewayCredentials $credentials): VerificationResult;

    /**
     * @throws GatewayRequestFailed
     */
    public function createPixCharge(GatewayCredentials $credentials, ChargeRequest $charge): PixCharge;

    /**
     * @throws GatewayRequestFailed
     */
    public function chargeCard(GatewayCredentials $credentials, ChargeRequest $charge, CardPayment $card): ChargeSnapshot;

    /**
     * @throws GatewayRequestFailed
     */
    public function createHostedCheckout(GatewayCredentials $credentials, ChargeRequest $charge, PaymentMethod $method, int $installments, string $returnUrl): HostedCheckout;

    /**
     * @throws GatewayRequestFailed
     */
    public function fetch(GatewayCredentials $credentials, string $chargeId): ChargeSnapshot;

    /**
     * @throws GatewayRequestFailed
     */
    public function cancel(GatewayCredentials $credentials, string $chargeId): void;

    /**
     * @throws GatewayRequestFailed
     */
    public function refund(GatewayCredentials $credentials, string $chargeId, int $amountCents): RefundResult;

    /**
     * @param  array<string, string>  $headers
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(GatewayCredentials $credentials, array $headers, string $body): WebhookEvent;
}
