<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('documenta toda rota de /api/v1 no OpenAPI gerado pelo Scramble', function (): void {
    $user = new User(['id' => 1, 'name' => 'Teste', 'email' => 'teste@phpeste.dev']);

    $response = $this->actingAs($user)->getJson('/docs/api.json');

    $response->assertOk();

    $documentedPaths = collect($response->json('paths'))->keys();

    $apiV1Uris = collect(Route::getRoutes())
        ->map(fn ($route) => $route->uri())
        ->filter(fn (string $uri): bool => str_starts_with($uri, 'api/v1/'))
        ->map(fn (string $uri): string => '/'.Str::after($uri, 'api/'));

    expect($apiV1Uris)->not->toBeEmpty();

    foreach ($apiV1Uris as $uri) {
        expect($documentedPaths)->toContain($uri);
    }
});
