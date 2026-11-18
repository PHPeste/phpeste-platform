<?php

declare(strict_types=1);

use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->travelTo('2027-03-10 12:00:00');
    $this->laptop = $this->user->createToken('Painel PHPeste')->plainTextToken;
    $this->travel(1)->hour();
    $this->phone = $this->user->createToken('App PHPeste · Android')->plainTextToken;
});

it('lista as sessões ativas e marca a atual', function (): void {
    $this->withToken($this->phone)->getJson('/api/v1/me/sessions');
    resolve('auth')->forgetGuards();

    $sessions = $this->withToken($this->laptop)->getJson('/api/v1/me/sessions')->assertOk()->json('data');

    expect(collect($sessions)->pluck('name')->all())->toContain('Painel PHPeste', 'App PHPeste · Android')
        ->and(collect($sessions)->firstWhere('name', 'Painel PHPeste')['current'])->toBeTrue()
        ->and(collect($sessions)->firstWhere('name', 'App PHPeste · Android')['current'])->toBeFalse();
});

it('encerrar a sessão de outro dispositivo revoga o token dele', function (): void {
    $sessions = $this->withToken($this->laptop)->getJson('/api/v1/me/sessions')->json('data');
    $phone = collect($sessions)->firstWhere('name', 'App PHPeste · Android');

    $this->withToken($this->laptop)->deleteJson("/api/v1/me/sessions/{$phone['id']}")->assertNoContent();

    resolve('auth')->forgetGuards();
    $this->withToken($this->phone)->getJson('/api/v1/me')->assertUnauthorized();
    resolve('auth')->forgetGuards();
    $this->withToken($this->laptop)->getJson('/api/v1/me')->assertOk();
});

it('não encerra sessão de outra pessoa', function (): void {
    $other = User::factory()->create();
    $token = $other->createToken('Outro');

    $this->withToken($this->laptop)->deleteJson("/api/v1/me/sessions/{$token->accessToken->getKey()}")->assertNotFound();
    expect($other->tokens()->count())->toBe(1);
});

it('guarda as preferências de notificação e devolve rótulo e descrição de cada uma', function (): void {
    $this->withToken($this->laptop)->getJson('/api/v1/me')
        ->assertJsonPath('user.notifications.0', ['id' => 'proposals_to_review', 'label' => 'Novas propostas para avaliar', 'description' => 'Quando a organização atribui propostas para você revisar.', 'enabled' => true])
        ->assertJsonPath('user.notifications.3.enabled', false);

    $this->withToken($this->laptop)->patchJson('/api/v1/me', ['notifications' => ['weekly_summary' => true, 'payment_failures' => false]])
        ->assertOk()
        ->assertJsonPath('data.notifications.1.enabled', false)
        ->assertJsonPath('data.notifications.3.enabled', true)
        ->assertJsonPath('data.notifications.0.enabled', true);

    $this->withToken($this->laptop)->patchJson('/api/v1/me', ['notifications' => ['inexistente' => true]])->assertStatus(422);
});
