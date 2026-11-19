<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Tasks\Board\TaskLinks;
use PHPeste\Tasks\Cloning\TasksCloner;
use PHPeste\Tasks\Console\SendDueReminders;
use PHPeste\Tasks\Overview\TasksOverviewContributor;

class TasksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TaskLinks::class);
        $this->app->tag([TasksOverviewContributor::class], EditionOverviewContributor::TAG);
        $this->app->tag([TasksCloner::class], EditionCloner::TAG);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SendDueReminders::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('tasks:due-reminders')->dailyAt('08:00')->timezone('America/Sao_Paulo')->withoutOverlapping();
        });
    }
}
