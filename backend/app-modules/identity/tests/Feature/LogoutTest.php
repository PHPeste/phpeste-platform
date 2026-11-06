<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use PHPeste\Identity\Models\User;

// O guard do Sanctum fica em cache por request simulado: sem isso, a segunda
// chamada dentro do mesmo teste reaproveitaria o usuário já resolvido na
// primeira, mesmo com o token apagado do banco. Via HTTP de verdade (cada
// requisição é um processo novo) isso não existe — confirmado manualmente.
function forgetResolvedAuthGuard(): void
{
    Auth::forgetGuards();
}

it('revoga o token atual no logout e ele para de funcionar imediatamente', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson(['loggedOut' => true]);

    forgetResolvedAuthGuard();

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

it('logout-all revoga todos os tokens do usuário', function (): void {
    $user = User::factory()->create();
    $tokenA = $user->createToken('dispositivo-a')->plainTextToken;
    $tokenB = $user->createToken('dispositivo-b')->plainTextToken;

    $this->withToken($tokenA)
        ->postJson('/api/v1/auth/logout-all')
        ->assertOk();

    forgetResolvedAuthGuard();
    $this->withToken($tokenA)->getJson('/api/v1/me')->assertStatus(401);

    forgetResolvedAuthGuard();
    $this->withToken($tokenB)->getJson('/api/v1/me')->assertStatus(401);
});

it('exige autenticação pra deslogar', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertStatus(401);
});
