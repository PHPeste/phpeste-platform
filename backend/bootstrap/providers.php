<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\SecurityServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    SecurityServiceProvider::class,
];
