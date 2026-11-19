<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Data\RefundRequest;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\RefundReason;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.sqlite.busy_timeout' => 10_000]);

[, $editionId, $reference, $chargeId, $apiKey, $amount, $startAt] = $argv;

FakeGateway::$charges[$chargeId] = ['amount' => (int) $amount, 'status' => ChargeStatus::Paid, 'api_key' => $apiKey];
$ledger = resolve(PaymentLedger::class);

time_sleep_until((float) $startAt);

try {
    $ledger->refund(new RefundRequest($editionId, $reference, (int) $amount, RefundReason::BuyerRequest, null, null));
    fwrite(STDOUT, 'ok');
} catch (ValidationException) {
    fwrite(STDOUT, 'refused');
} catch (Throwable $throwable) {
    fwrite(STDOUT, 'error: '.$throwable->getMessage());
}
