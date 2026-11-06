<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Notification::fake();
});

it('cadastra um usuário e envia e-mail de verificação', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@phpeste.dev',
        'password' => 'umasenhabemforte123',
        'password_confirmation' => 'umasenhabemforte123',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ada@phpeste.dev')
        ->assertJsonPath('data.email_verified_at', null)
        ->assertJsonMissingPath('data.password');

    $user = User::where('email', 'ada@phpeste.dev')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejeita senha fraca', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@phpeste.dev',
        'password' => '123',
        'password_confirmation' => '123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

it('rejeita e-mail duplicado', function (): void {
    User::factory()->create(['email' => 'ada@phpeste.dev']);

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Outra Ada',
        'email' => 'ada@phpeste.dev',
        'password' => 'umasenhabemforte123',
        'password_confirmation' => 'umasenhabemforte123',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('verifica o e-mail com um link assinado válido', function (): void {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->getJson($url)->assertOk()->assertJson(['verified' => true]);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejeita link de verificação com assinatura inválida', function (): void {
    $user = User::factory()->unverified()->create();

    $this->getJson("/api/v1/auth/email/verify/{$user->id}/hash-errado?expires=9999999999&signature=invalida")
        ->assertStatus(403);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('reenvia o e-mail de verificação pra quem está autenticado e ainda não verificou', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertOk()
        ->assertJson(['sent' => true]);

    Notification::assertSentTo($user, VerifyEmail::class);
});
