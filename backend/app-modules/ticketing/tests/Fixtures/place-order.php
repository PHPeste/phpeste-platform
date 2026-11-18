<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use PHPeste\Ticketing\Actions\PlaceOrder;
use PHPeste\Ticketing\Data\OrderAttendee;
use PHPeste\Ticketing\Data\OrderRequest;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['database.connections.sqlite.busy_timeout' => 10_000, 'database.connections.sqlite.transaction_mode' => 'IMMEDIATE']);

[, $editionId, $typeId, $key] = $argv;

$request = new OrderRequest('Ana Souza', 'ana@exemplo.com', '52998224725', [
    ['ticket_type_id' => $typeId, 'attendees' => [new OrderAttendee('Ana Souza', 'ana@exemplo.com', [])]],
], null, null, null);

$result = resolve(PlaceOrder::class)->handle($editionId, $request, $key);

fwrite(STDOUT, $result['order']->id);
