<?php

declare(strict_types=1);

namespace PHPeste\Branding\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Branding\Contracts\DnsResolver;
use PHPeste\Branding\Contracts\EditionThemes;
use PHPeste\Branding\Domains\SystemDnsResolver;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Policies\EditionSettingPolicy;
use PHPeste\Branding\Theme\CachedEditionThemes;

class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditionThemes::class, CachedEditionThemes::class);
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);
    }

    public function boot(): void
    {
        Gate::policy(EditionSetting::class, EditionSettingPolicy::class);
    }
}
