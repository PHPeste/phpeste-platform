<?php

declare(strict_types=1);

namespace PHPeste\Payments\Tests\Support;

use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Data\GatewayCredentials;

interface GatewayHarness
{
    public static function fixture(string $name): array;

    public function gateway(): PaymentGateway;

    public function credentials(): GatewayCredentials;

    public function invalidCredentials(): GatewayCredentials;

    public function stubAccount(): void;

    public function stubPixCreated(): string;

    public function stubPaid(string $chargeId): int;

    public function stubRefunded(string $chargeId): void;

    public function stubCancelled(string $chargeId): void;

    public function stubOutage(string $chargeId): void;

    /**
     * @return array{headers: array<string, string>, body: string, charge_id: string}
     */
    public function signedWebhook(): array;

    public function sentAmount(): float;
}
