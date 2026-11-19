<?php

declare(strict_types=1);

namespace PHPeste\Payments\Gateway;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Enums\GatewayCapability;

class GatewayRegistry
{
    /**
     * @param  array<string, class-string<PaymentGateway>>  $drivers
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $drivers,
    ) {}

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->drivers);
    }

    public function driver(string $key): PaymentGateway
    {
        throw_unless($this->has($key), InvalidArgumentException::class, "Gateway de pagamento desconhecido: {$key}");

        $driver = $this->container->make($this->drivers[$key]);

        throw_unless($driver instanceof PaymentGateway, InvalidArgumentException::class, "O driver {$key} não implementa PaymentGateway.");

        return $driver;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * @return list<array{key: string, label: string, needs_public_key: bool}>
     */
    public function available(): array
    {
        return array_map(fn (string $key): array => ['key' => $key, 'label' => $this->driver($key)->label(), 'needs_public_key' => $this->needsPublicKey($key)], $this->keys());
    }

    public function needsPublicKey(string $key): bool
    {
        return $this->has($key) && in_array(GatewayCapability::CardToken, $this->driver($key)->capabilities(), true) && !in_array(GatewayCapability::HostedCard, $this->driver($key)->capabilities(), true);
    }
}
