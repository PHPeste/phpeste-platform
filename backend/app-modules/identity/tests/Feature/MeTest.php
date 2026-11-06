<?php

declare(strict_types=1);

use PHPeste\Identity\Models\User;

it('devolve o perfil autenticado com roles vazio', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('pest')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'user' => ['id' => $user->id, 'email' => $user->email],
            'roles' => [],
        ]);
});

it('exige autenticação', function (): void {
    $this->getJson('/api/v1/me')->assertStatus(401);
});
