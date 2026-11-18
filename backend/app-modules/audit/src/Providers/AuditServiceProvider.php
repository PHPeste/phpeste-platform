<?php

declare(strict_types=1);

namespace PHPeste\Audit\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use PHPeste\Audit\Console\PruneAuditLogsCommand;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->commands([PruneAuditLogsCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('audit:prune')->dailyAt('03:30')->withoutOverlapping();
        });
    }
}
