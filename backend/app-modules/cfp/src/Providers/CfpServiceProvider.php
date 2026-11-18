<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Cfp\Console\SyncCfpStatusCommand;
use PHPeste\Cfp\Contracts\ReviewLedger;
use PHPeste\Cfp\Proposals\ProposalParticipation;
use PHPeste\Cfp\Reviews\EmptyReviewLedger;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Identity\Contracts\EditionParticipation;

class CfpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReviewLedger::class, EmptyReviewLedger::class);
        $this->app->tag([ProposalParticipation::class], EditionParticipation::TAG);
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

        Gate::define('cfp.view-proposals', fn (Authenticatable $user): bool => Gate::forUser($user)->any([
            EditionPermission::ManageProgram->value,
            EditionPermission::ReviewProposals->value,
        ]));
    }
}
