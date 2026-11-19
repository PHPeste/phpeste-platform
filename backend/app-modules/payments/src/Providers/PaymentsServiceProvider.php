<?php

declare(strict_types=1);

namespace PHPeste\Payments\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Payments\Console\ReencryptCredentials;
use PHPeste\Payments\Console\RetryWebhooksCommand;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Contracts\PaymentGateway;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Gateway\ChargeService;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Gateway\PaymentAccountDirectory;
use PHPeste\Payments\Gateway\PaymentLedgerService;
use PHPeste\Payments\Overview\PaymentsOverviewContributor;

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
        $this->app->bind(PaymentProcessor::class, ChargeService::class);
        $this->app->bind(PaymentLedger::class, PaymentLedgerService::class);
        $this->app->tag([PaymentsOverviewContributor::class], EditionOverviewContributor::TAG);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ReencryptCredentials::class, RetryWebhooksCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('payments:retry-webhooks')->everyMinute()->withoutOverlapping();
        });
    }
}
