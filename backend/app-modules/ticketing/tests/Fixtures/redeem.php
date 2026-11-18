<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Actions\RedeemCoupon;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.sqlite.busy_timeout' => 10_000]);

[, $editionId, $couponId] = $argv;

try {
    resolve(RedeemCoupon::class)->handle($editionId, $couponId, 1_000);
    fwrite(STDOUT, 'ok');
} catch (ValidationException) {
    fwrite(STDOUT, 'refused');
}
