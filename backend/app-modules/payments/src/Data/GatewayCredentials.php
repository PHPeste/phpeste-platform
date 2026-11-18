<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use PHPeste\Payments\Enums\GatewayEnvironment;
use SensitiveParameter;

final readonly class GatewayCredentials
{
    public function __construct(
        #[SensitiveParameter]
        public string $apiKey,
        #[SensitiveParameter]
        public string $webhookSecret,
        public GatewayEnvironment $environment,
    ) {}

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['environment' => $this->environment->value];
    }
}
