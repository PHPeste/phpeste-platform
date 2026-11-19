<?php

declare(strict_types=1);

use PHPeste\Payments\Drivers\Asaas\AsaasGateway;
use PHPeste\Payments\Drivers\FakeGateway;

return [
    'drivers' => array_filter([
        'asaas' => AsaasGateway::class,
        'fake' => env('PAYMENTS_FAKE_DRIVER', env('APP_ENV') !== 'production') ? FakeGateway::class : null,
    ]),
];
