<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\App;
use PHPeste\Identity\Models\User;

it('usa pt_BR como idioma e en como fallback', function (): void {
    expect(App::getLocale())->toBe('pt_BR')
        ->and(App::getFallbackLocale())->toBe('en');
});

it('devolve as mensagens de validação em português', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'O campo e-mail é obrigatório.')
        ->assertJsonPath('errors.password.0', 'O campo senha é obrigatório.');
});

it('traduz a mensagem de valor já em uso', function (): void {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/register', [
        'name' => 'Maria',
        'email' => $user->email,
        'password' => 'umasenhabemforte123',
        'password_confirmation' => 'umasenhabemforte123',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Esse e-mail já está em uso.');
});

it('traduz o e-mail de confirmação de cadastro do Laravel', function (): void {
    $user = User::factory()->unverified()->create();
    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Confirme seu e-mail')
        ->and($mail->actionText)->toBe('Confirmar e-mail')
        ->and($mail->introLines)->toBe(['Clique no botão abaixo para confirmar seu e-mail.']);
});
