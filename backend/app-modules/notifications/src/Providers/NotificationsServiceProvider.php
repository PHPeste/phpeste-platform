<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Tenancy\CurrentEdition;
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
        Gate::define('manage-edition-emails', function (Authenticatable $user): bool {
            $editionId = resolve(CurrentEdition::class)->id();

            return $editionId !== null
                && resolve(EditionMemberships::class)->roleIn($editionId, (string) $user->getAuthIdentifier()) === EditionRole::Organizer;
        });
    }
}
