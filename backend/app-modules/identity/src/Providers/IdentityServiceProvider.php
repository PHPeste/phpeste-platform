<?php

declare(strict_types=1);

namespace PHPeste\Identity\Providers;

use Illuminate\Support\ServiceProvider;
use PHPeste\Identity\Actions\EloquentSpeakerProfiles;
use PHPeste\Identity\Actions\EloquentUserAccounts;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Contracts\UserAccounts;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserAccounts::class, EloquentUserAccounts::class);
        $this->app->bind(SpeakerProfiles::class, EloquentSpeakerProfiles::class);
    }

    public function boot(): void {}
}
