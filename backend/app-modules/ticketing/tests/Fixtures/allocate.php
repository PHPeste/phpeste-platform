<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Actions\AllocateTickets;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.sqlite.busy_timeout' => 10_000]);

[, $editionId, $batchId, $quantity] = $argv;

try {
    resolve(AllocateTickets::class)->handle($editionId, $batchId, (int) $quantity);
    fwrite(STDOUT, 'ok');
} catch (ValidationException) {
    fwrite(STDOUT, 'sold_out');
}
