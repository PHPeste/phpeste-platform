<?php

declare(strict_types=1);

use PHPeste\Payments\Drivers\FakeGateway;

return [
    'drivers' => array_filter([
        'fake' => env('PAYMENTS_FAKE_DRIVER', env('APP_ENV') !== 'production') ? FakeGateway::class : null,
    ]),
];
