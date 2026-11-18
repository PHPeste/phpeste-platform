<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('aplica o grupo api em toda rota de /api/v1', function (): void {
    $routes = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/'));

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->gatherMiddleware())->toContain('api');
    }
});
