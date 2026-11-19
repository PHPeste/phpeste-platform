<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Providers;

use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Suppliers\Access\EloquentEditionSuppliers;
use PHPeste\Suppliers\Access\SupplierTaskLinks;
use PHPeste\Suppliers\Cloning\SuppliersCloner;
use PHPeste\Suppliers\Contracts\EditionSuppliers;
use PHPeste\Suppliers\Http\People;
use PHPeste\Suppliers\Overview\SuppliersOverviewContributor;
use PHPeste\Tasks\Contracts\TaskLinkProvider;

class SuppliersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/suppliers.php', 'suppliers');
        $this->app->scoped(People::class);
        $this->app->bind(EditionSuppliers::class, EloquentEditionSuppliers::class);
        $this->app->tag([SuppliersOverviewContributor::class], EditionOverviewContributor::TAG);
        $this->app->tag([SuppliersCloner::class], EditionCloner::TAG);
        $this->app->tag([SupplierTaskLinks::class], TaskLinkProvider::TAG);
    }

    public function boot(): void {}
}
