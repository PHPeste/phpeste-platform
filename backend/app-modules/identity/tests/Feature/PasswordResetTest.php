<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Notification;
use PHPeste\Identity\Models\User;
use PHPeste\Identity\Notifications\ResetPasswordNotification;

beforeEach(function (): void {
    Notification::fake();
    config(['app.admin_url' => 'https://admin.phpeste.dev']);
    $this->user = User::factory()->create(['email' => 'ada@phpeste.dev', 'password' => 'senhaantigaforte123']);
});

function requestResetToken(object $test): string
{
    $test->postJson('/api/v1/auth/password/forgot', ['email' => 'ada@phpeste.dev'])->assertOk();

    $token = null;

    Notification::assertSentTo($test->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    return (string) $token;
}

it('envia o link de redefinição apontando para o painel', function (): void {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ADA@phpeste.dev'])
        ->assertOk()
        ->assertJsonPath('sent', true);

    Notification::assertSentTo($this->user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification): bool {
        $mail = $notification->toMail($this->user);

        return str_starts_with($notification->url($this->user), 'https://admin.phpeste.dev/redefinir-senha?token=')
            && str_contains($notification->url($this->user), 'email=ada%40phpeste.dev')
            && $mail->subject === 'Redefinir sua senha'
            && in_array('O link vale por 30 minutos e só pode ser usado uma vez.', array_merge($mail->introLines, $mail->outroLines), true);
    });
});

it('responde igual para e-mail que não existe, sem enviar nada', function (): void {
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ninguem@phpeste.dev'])
        ->assertOk()
        ->assertJsonPath('message', 'Se existir uma conta com este e-mail, enviamos um link para redefinir a senha.');

    Notification::assertNothingSent();
});

it('redefine a senha, derruba todas as sessões e o link só vale uma vez', function (): void {
    $this->user->createToken('celular');
    $this->user->createToken('notebook');

    $token = requestResetToken($this);
    $payload = ['email' => 'ada@phpeste.dev', 'token' => $token, 'password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'];

    $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk()->assertJsonPath('reset', true);

    expect($this->user->tokens()->count())->toBe(0);

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@phpeste.dev', 'password' => 'senhaantigaforte123'])->assertStatus(401);
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@phpeste.dev', 'password' => 'senhanovaforte456'])->assertOk();

    $this->postJson('/api/v1/auth/password/reset', [...$payload, 'password' => 'outrasenhaforte789', 'password_confirmation' => 'outrasenhaforte789'])
        ->assertStatus(422)
        ->assertJsonPath('errors.token.0', 'Este link de redefinição é inválido ou expirou. Peça um novo.');
});

it('recusa link expirado', function (): void {
    $token = requestResetToken($this);

    $this->travel(31)->minutes();

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ada@phpeste.dev', 'token' => $token, 'password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);
});

it('recusa token de outra pessoa e senha fraca', function (): void {
    $token = requestResetToken($this);
    User::factory()->create(['email' => 'outra@phpeste.dev']);

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'outra@phpeste.dev', 'token' => $token, 'password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['token']);

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ada@phpeste.dev', 'token' => $token, 'password' => '123', 'password_confirmation' => '123'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});
