<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Cfp\Console\SyncCfpStatusCommand;
use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Reviews\EmptyReviewLedger;
use PHPeste\Community\Enums\EditionPermission;

class CfpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReviewLedger::class, EmptyReviewLedger::class);
    }

    public function boot(): void
    {
        $this->commands([SyncCfpStatusCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('cfp:sync-status')->everyMinute()->withoutOverlapping();
        });

        Gate::define('cfp.view-settings', fn (Authenticatable $user): bool => Gate::forUser($user)->any([
            EditionPermission::ManageEdition->value,
            EditionPermission::ReviewProposals->value,
        ]));
    }
}
