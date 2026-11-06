<?php

declare(strict_types=1);

use PHPeste\Identity\Models\User;

it('autentica com credenciais corretas e devolve token', function (): void {
    $user = User::factory()->create(['password' => 'umasenhabemforte123']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'umasenhabemforte123',
        'device_name' => 'pest',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'pest',
    ]);
});

it('usa o user agent como nome do dispositivo quando device_name não é enviado', function (): void {
    $user = User::factory()->create(['password' => 'umasenhabemforte123']);

    $this->withHeader('User-Agent', 'TesteAgent/1.0')
        ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'umasenhabemforte123'])
        ->assertOk();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'TesteAgent/1.0',
    ]);
});

it('rejeita senha errada com 401, sem criar token', function (): void {
    $user = User::factory()->create(['password' => 'umasenhabemforte123']);

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senhaerrada'])
        ->assertStatus(401)
        ->assertJson(['code' => 'unauthenticated']);

    $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
});

it('rejeita e-mail inexistente com 401', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'ninguem@phpeste.dev', 'password' => 'qualquercoisa'])
        ->assertStatus(401);
});

it('bloqueia depois de várias tentativas erradas seguidas', function (): void {
    $user = User::factory()->create(['password' => 'umasenhabemforte123']);
    $attempts = (int) config('security.rate_limits.login.attempts');

    for ($i = 0; $i < $attempts; $i++) {
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'errada'])
            ->assertStatus(401);
    }

    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'umasenhabemforte123'])
        ->assertStatus(429);
});
