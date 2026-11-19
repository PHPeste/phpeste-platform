<?php

declare(strict_types=1);

use PHPeste\Payments\Drivers\Asaas\AsaasGateway;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Drivers\MercadoPago\MercadoPagoGateway;

return [
    'drivers' => array_filter([
        'asaas' => AsaasGateway::class,
        'mercadopago' => MercadoPagoGateway::class,
        'fake' => env('PAYMENTS_FAKE_DRIVER', env('APP_ENV') !== 'production') ? FakeGateway::class : null,
    ]),
];
