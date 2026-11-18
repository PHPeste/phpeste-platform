<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Ticketing\Cloning\TicketingCloner;
use PHPeste\Ticketing\Console\ExpireOrdersCommand;
use PHPeste\Ticketing\Console\RotateTicketBatches;
use PHPeste\Ticketing\Overview\TicketingOverviewContributor;

class TicketingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->tag([TicketingCloner::class], EditionCloner::TAG);
        $this->app->tag([TicketingOverviewContributor::class], EditionOverviewContributor::TAG);
    }

    public function boot(): void
    {
        Gate::define('ticketing.view', fn (Authenticatable $user): bool => Gate::forUser($user)->any([EditionPermission::ManageEdition->value, EditionPermission::ViewOrders->value]));

        if ($this->app->runningInConsole()) {
            $this->commands([RotateTicketBatches::class, ExpireOrdersCommand::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('ticketing:rotate-batches')->everyMinute()->withoutOverlapping();
            $schedule->command('ticketing:expire-orders')->everyMinute()->withoutOverlapping();
        });
    }
}
