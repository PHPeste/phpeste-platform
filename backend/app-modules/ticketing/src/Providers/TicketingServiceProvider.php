<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Contracts\EditionTransitionGuard;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Payments\Contracts\AccountUsage;
use PHPeste\Ticketing\Cloning\TicketingCloner;
use PHPeste\Ticketing\Console\ExpireOrdersCommand;
use PHPeste\Ticketing\Console\ReconcileOrdersCommand;
use PHPeste\Ticketing\Console\ReencryptOrders;
use PHPeste\Ticketing\Console\ResumeStalledImports;
use PHPeste\Ticketing\Console\RotateTicketBatches;
use PHPeste\Ticketing\Contracts\TicketVerifier;
use PHPeste\Ticketing\Orders\OrderLedger;
use PHPeste\Ticketing\Orders\SalesReadinessGuard;
use PHPeste\Ticketing\Overview\TicketingOverviewContributor;
use PHPeste\Ticketing\Tickets\TicketPasses;

class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/ticketing.php', 'ticketing');
        $this->app->bind(TicketVerifier::class, TicketPasses::class);
        $this->app->tag([TicketingCloner::class], EditionCloner::TAG);
        $this->app->tag([TicketingOverviewContributor::class], EditionOverviewContributor::TAG);
        $this->app->tag([SalesReadinessGuard::class], EditionTransitionGuard::TAG);
        $this->app->bind(AccountUsage::class, OrderLedger::class);
    }

    public function boot(): void
    {
        Gate::define('ticketing.view', fn (Authenticatable $user): bool => Gate::forUser($user)->any([EditionPermission::ManageEdition->value, EditionPermission::ViewOrders->value]));

        if ($this->app->runningInConsole()) {
            $this->commands([RotateTicketBatches::class, ExpireOrdersCommand::class, ReencryptOrders::class, ReconcileOrdersCommand::class, ResumeStalledImports::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ticketing:rotate-batches')->everyMinute()->withoutOverlapping();
            $schedule->command('ticketing:expire-orders')->everyMinute()->withoutOverlapping();
            $schedule->command('ticketing:resume-imports')->everyFiveMinutes()->withoutOverlapping();
            $schedule->command('ticketing:reconcile')->dailyAt('04:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
        });
    }
}
