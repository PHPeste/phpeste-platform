<?php

declare(strict_types=1);

use Illuminate\Auth\Notifications\VerifyEmail;
use PHPeste\Identity\Models\User;
use PHPeste\Identity\Notifications\ResetPasswordNotification;

it('renderiza o e-mail de redefinição de senha com o template de notificação do Laravel', function (): void {
    config(['app.admin_url' => 'https://admin.phpeste.dev']);
    $user = User::factory()->create(['name' => 'Ada']);

    $html = (string) new ResetPasswordNotification('token-de-teste')->toMail($user)->render();

    expect($html)->toContain('Oi, Ada!', 'Escolher nova senha', 'https://admin.phpeste.dev/redefinir-senha?token=token-de-teste');
});

it('renderiza o e-mail de verificação de cadastro', function (): void {
    $user = User::factory()->unverified()->create();

    $html = (string) (new VerifyEmail)->toMail($user)->render();

    expect($html)->toContain('/api/v1/auth/email/verify/');
});
