<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use PHPeste\Payments\Enums\GatewayEnvironment;
use PHPeste\Payments\Enums\HolderType;
use PHPeste\Payments\Enums\PaymentMethod;

final readonly class ReceivingAccount
{
    /**
     * @param  list<PaymentMethod>  $methods
     */
    public function __construct(
        public string $id,
        public string $editionId,
        public string $driver,
        public GatewayEnvironment $environment,
        public HolderType $holderType,
        public string $holderName,
        public array $methods,
        public int $maxInstallments,
        public int $pixExpirationMinutes,
        public bool $ready,
        public ?string $publicKey = null,
        public ?string $holderDocumentMasked = null,
        public bool $retired = false,
    ) {}
}
