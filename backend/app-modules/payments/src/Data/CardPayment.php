<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use SensitiveParameter;

final readonly class CardPayment
{
    public function __construct(
        #[SensitiveParameter]
        public string $token,
        public int $installments,
        public ?string $remoteIp = null,
        public ?string $paymentMethodId = null,
        public ?string $issuerId = null,
    ) {}
}
