<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Providers;

use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Sponsors\Cloning\SponsorTiersCloner;
use PHPeste\Sponsors\Contracts\SponsorRevenue;
use PHPeste\Sponsors\Http\People;
use PHPeste\Sponsors\Overview\SponsorRevenueReport;
use PHPeste\Sponsors\Overview\SponsorshipTaskLinks;
use PHPeste\Sponsors\Overview\SponsorsOverviewContributor;
use PHPeste\Tasks\Contracts\TaskLinkProvider;

class SponsorsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/sponsors.php', 'sponsors');
        $this->app->scoped(People::class);
        $this->app->bind(SponsorRevenue::class, SponsorRevenueReport::class);
        $this->app->tag([SponsorsOverviewContributor::class], EditionOverviewContributor::TAG);
        $this->app->tag([SponsorTiersCloner::class], EditionCloner::TAG);
        $this->app->tag([SponsorshipTaskLinks::class], TaskLinkProvider::TAG);
    }

    public function boot(): void {}
}
