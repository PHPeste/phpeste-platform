<?php

declare(strict_types=1);

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * `internachi/modular` carrega as rotas de cada módulo num hook
     * `booting()` disparado antes do `boot()` de qualquer provider — por
     * isso a macro `apiV1` precisa existir já no `register()`, não no
     * `boot()`, senão os módulos tentam usar uma macro que ainda não existe.
     */
    public function register(): void
    {
        $this->registerApiRouteMacro();
    }

    public function boot(): void
    {
        $this->registerApiDocsGroupingByModule();

        Gate::define('viewApiDocs', fn (?Authenticatable $user = null): bool => $user instanceof Authenticatable);
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
