<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Notifications\Actions\QueueEmail;
use PHPeste\Notifications\Contracts\EditionMailer;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(EditionMailer::class, QueueEmail::class);
    }

    public function boot(): void
    {
        Gate::define('manage-edition-emails', fn (Authenticatable $user): bool => Gate::forUser($user)->allows(EditionPermission::ManageEdition->value));
    }
}
