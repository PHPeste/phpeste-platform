<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Cfp\Console\ProcessConfirmationDeadlinesCommand;
use PHPeste\Cfp\Console\SyncCfpStatusCommand;
use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Proposals\CfpCloner;
use PHPeste\Cfp\Proposals\CfpOverviewContributor;
use PHPeste\Cfp\Proposals\ProposalParticipation;
use PHPeste\Cfp\Reviews\EloquentReviewLedger;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Identity\Contracts\EditionParticipation;

class CfpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReviewLedger::class, EloquentReviewLedger::class);
        $this->app->tag([ProposalParticipation::class], EditionParticipation::TAG);
        $this->app->tag([CfpCloner::class], EditionCloner::TAG);
        $this->app->tag([CfpOverviewContributor::class], EditionOverviewContributor::TAG);
    }

    public function boot(): void
    {
        $this->commands([SyncCfpStatusCommand::class, ProcessConfirmationDeadlinesCommand::class]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('cfp:sync-status')->everyMinute()->withoutOverlapping();
            $schedule->command('cfp:confirmation-deadlines')->hourly()->withoutOverlapping();
        });

        Gate::define('cfp.view-settings', fn (Authenticatable $user): bool => Gate::forUser($user)->any([
            EditionPermission::ManageEdition->value,
            EditionPermission::ReviewProposals->value,
        ]));

        Gate::define('cfp.view-proposals', fn (Authenticatable $user): bool => Gate::forUser($user)->any([
            EditionPermission::ManageProgram->value,
            EditionPermission::ReviewProposals->value,
        ]));
    }
}
