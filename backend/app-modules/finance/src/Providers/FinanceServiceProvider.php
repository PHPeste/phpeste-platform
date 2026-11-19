<?php

declare(strict_types=1);

namespace PHPeste\Finance\Providers;

use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Finance\Cloning\FinanceCloner;
use PHPeste\Finance\Overview\FinanceOverviewContributor;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/finance.php', 'finance');
        $this->app->tag([FinanceOverviewContributor::class], EditionOverviewContributor::TAG);
        $this->app->tag([FinanceCloner::class], EditionCloner::TAG);
    }

    public function boot(): void {}
}
