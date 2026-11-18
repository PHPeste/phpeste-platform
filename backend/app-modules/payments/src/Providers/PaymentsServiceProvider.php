<?php

declare(strict_types=1);

namespace PHPeste\Payments\Providers;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use PHPeste\Payments\Console\ReencryptCredentials;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Gateway\PaymentAccountDirectory;

class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/payments.php', 'payments');

        $this->app->singleton(function (Container $app): GatewayRegistry {
            /** @var array<string, class-string<PaymentGateway>> $drivers */
            $drivers = (array) config('payments.drivers', []);

            return new GatewayRegistry($app, $drivers);
        });

        $this->app->bind(PaymentAccounts::class, PaymentAccountDirectory::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReencryptCredentials::class]);
        }
    }
}
