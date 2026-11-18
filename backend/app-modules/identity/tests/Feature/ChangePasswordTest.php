<?php

declare(strict_types=1);

use PHPeste\Identity\Models\User;

it('troca a senha conferindo a atual e encerra as outras sessões', function (): void {
    $user = User::factory()->create(['password' => 'senhaantigaforte123']);
    $current = $user->createToken('notebook')->plainTextToken;
    $user->createToken('celular');
    $user->createToken('tablet');

    $this->withToken($current)
        ->putJson('/api/v1/auth/password', ['current_password' => 'senhaantigaforte123', 'password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertOk()
        ->assertJsonPath('updated', true);

    expect($user->tokens()->pluck('name')->all())->toBe(['notebook']);

    $this->withToken($current)->getJson('/api/v1/me')->assertOk();
    $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'senhanovaforte456'])->assertOk();
});

it('recusa quando a senha atual não confere', function (): void {
    $user = User::factory()->create(['password' => 'senhaantigaforte123']);

    $this->withToken($user->createToken('pest')->plainTextToken)
        ->putJson('/api/v1/auth/password', ['current_password' => 'errada', 'password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertStatus(422)
        ->assertJsonPath('errors.current_password.0', 'A senha atual não confere.');

    $this->withToken($user->createToken('pest2')->plainTextToken)
        ->putJson('/api/v1/auth/password', ['password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertStatus(422);
});

it('deixa quem entrou só pelo GitHub definir uma senha sem informar a atual', function (): void {
    $user = User::factory()->create(['password' => null]);

    $this->withToken($user->createToken('pest')->plainTextToken)
        ->putJson('/api/v1/auth/password', ['password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertOk();

    expect($user->refresh()->hasPassword())->toBeTrue();
});

it('exige autenticação', function (): void {
    $this->putJson('/api/v1/auth/password', ['password' => 'x'])->assertStatus(401);
});
