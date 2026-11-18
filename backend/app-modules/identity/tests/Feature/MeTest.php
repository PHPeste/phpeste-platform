<?php

declare(strict_types=1);

use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
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

it('lista os papéis da pessoa em cada edição', function (): void {
    $user = User::factory()->create();
    $organized = Edition::factory()->create();
    $reviewed = Edition::factory()->create();
    EditionMember::factory()->for($organized)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer, 'created_at' => now()->subDay()]);
    EditionMember::factory()->for($reviewed)->create(['user_id' => $user->id, 'role' => EditionRole::Reviewer]);
    EditionMember::factory()->for($reviewed)->create(['user_id' => User::factory()->create()->id, 'role' => EditionRole::Organizer]);

    $this->withToken($user->createToken('pest')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('roles', [
            ['edition_id' => $organized->id, 'role' => 'organizador'],
            ['edition_id' => $reviewed->id, 'role' => 'avaliador'],
        ]);
});

it('exige autenticação', function (): void {
    $this->getJson('/api/v1/me')->assertStatus(401);
});
