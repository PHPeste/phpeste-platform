<?php

declare(strict_types=1);

namespace PHPeste\Branding\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Branding\Contracts\DnsResolver;
use PHPeste\Branding\Contracts\EditionSites;
use PHPeste\Branding\Contracts\EditionThemes;
use PHPeste\Branding\Domains\PrimaryDomainSites;
use PHPeste\Branding\Domains\SystemDnsResolver;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Policies\EditionSettingPolicy;
use PHPeste\Branding\Theme\BrandingOverviewContributor;
use PHPeste\Branding\Theme\CachedEditionThemes;
use PHPeste\Branding\Theme\ThemeCloner;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Contracts\EditionOverviewContributor;

class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditionThemes::class, CachedEditionThemes::class);
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
        $this->app->bind(EditionSites::class, PrimaryDomainSites::class);
        $this->app->tag([ThemeCloner::class], EditionCloner::TAG);
        $this->app->tag([BrandingOverviewContributor::class], EditionOverviewContributor::TAG);
    }

    public function boot(): void
    {
        Gate::policy(EditionSetting::class, EditionSettingPolicy::class);
    }
}
