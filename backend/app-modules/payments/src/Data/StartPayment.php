<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

use Illuminate\Support\Carbon;
use PHPeste\Payments\Enums\PaymentMethod;
use SensitiveParameter;

final readonly class StartPayment
{
    public function __construct(
        public string $editionId,
        public string $accountId,
        public string $reference,
        public int $amountCents,
        public string $description,
        public Payer $payer,
        public PaymentMethod $method,
        public int $installments,
        public Carbon $expiresAt,
        public string $returnUrl,
        #[SensitiveParameter]
        public ?string $cardToken = null,
        public ?string $remoteIp = null,
    ) {}
}
