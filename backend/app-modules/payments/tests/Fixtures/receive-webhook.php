<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use PHPeste\Payments\Actions\ReceiveWebhook;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Models\PaymentAccount;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.sqlite.busy_timeout' => 10_000]);

[, $accountId, $body, $secret, $startAt] = $argv;

$account = PaymentAccount::query()->withoutGlobalScopes()->findOrFail($accountId);
$receive = resolve(ReceiveWebhook::class);
$headers = [FakeGateway::SIGNATURE_HEADER => FakeGateway::sign($body, $secret)];

time_sleep_until((float) $startAt);

try {
    $record = $receive->handle($account, $headers, $body);
    fwrite(STDOUT, $record->wasRecentlyCreated ? 'created' : 'duplicate');
} catch (Throwable $throwable) {
    fwrite(STDOUT, 'error: '.$throwable->getMessage());
}
