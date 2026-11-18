<?php

declare(strict_types=1);

namespace PHPeste\Community\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Contracts\EditionMemberships;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Http\Middleware\ResolveEdition;
use PHPeste\Community\Http\Middleware\ResolvePublishedEdition;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Policies\CommunityPolicy;
use PHPeste\Community\Policies\EditionPolicy;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Community\Tenancy\EloquentEditionDirectory;
use PHPeste\Community\Tenancy\EloquentEditionMemberships;
use PHPeste\Community\Tenancy\TeamParticipation;
use PHPeste\Identity\Contracts\EditionParticipation;

class CommunityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentEdition::class);
        $this->app->bind(EditionMemberships::class, EloquentEditionMemberships::class);
        $this->app->bind(EditionDirectory::class, EloquentEditionDirectory::class);
        $this->app->tag([TeamParticipation::class], EditionParticipation::TAG);

        Route::macro('apiV1Edition', function (?string $prefix = null): RouteRegistrar {
            $path = 'editions/{edition}'.($prefix ? "/{$prefix}" : '');

            return Route::apiV1($path, ['auth:sanctum', ResolveEdition::class])
                ->where(['edition' => '[0-9a-fA-F-]{36}']);
        });

        Route::macro('apiV1PublishedEdition', function (string $area, ?string $prefix = null, array $middleware = []): RouteRegistrar {
            $path = "{$area}/editions/{edition}".($prefix ? "/{$prefix}" : '');

            return Route::apiV1($path, [...$middleware, ResolvePublishedEdition::class])
                ->where(['edition' => '[0-9a-fA-F-]{36}']);
        });
    }

    public function boot(): void
    {
        Gate::policy(Community::class, CommunityPolicy::class);
        Gate::policy(Edition::class, EditionPolicy::class);

        foreach (EditionPermission::cases() as $permission) {
            Gate::define($permission->value, function (Authenticatable $user, ?string $editionId = null) use ($permission): bool {
                $editionId ??= resolve(CurrentEdition::class)->id();

                return $editionId !== null
                    && resolve(EditionMemberships::class)->can($editionId, (string) $user->getAuthIdentifier(), $permission);
            });
        }
    }
}
