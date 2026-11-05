<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerApiRouteMacro();
        $this->registerApiDocsGroupingByModule();

        Gate::define('viewApiDocs', fn (?User $user = null): bool => $user instanceof User);
    }

    private function registerApiRouteMacro(): void
    {
        Route::macro('apiV1', function (?string $prefix = null): RouteRegistrar {
            $path = 'api/v1'.($prefix ? "/{$prefix}" : '');

            return Route::middleware('api')->prefix($path);
        });
    }

    private function registerApiDocsGroupingByModule(): void
    {
        Scramble::resolveTagsUsing(function (RouteInfo $routeInfo): array {
            $className = $routeInfo->className();

            if ($className !== null && preg_match('/^PHPeste\\\\([^\\\\]+)\\\\/', $className, $matches) === 1) {
                return [$matches[1]];
            }

            $default = $className !== null
                ? Str::of(class_basename($className))->replace('Controller', '')->toString()
                : null;

            return array_filter([$default]);
        });
    }
}
