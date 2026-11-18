<?php

declare(strict_types=1);

namespace PHPeste\Branding\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Policies\EditionSettingPolicy;

class BrandingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(EditionSetting::class, EditionSettingPolicy::class);
    }
}
