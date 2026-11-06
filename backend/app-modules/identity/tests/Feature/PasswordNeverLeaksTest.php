<?php

declare(strict_types=1);

it('nunca devolve a senha na resposta de cadastro, login ou /me', function (): void {
    $register = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ada Lovelace',
        'email' => 'ada@phpeste.dev',
        'password' => 'umasenhabemforte123',
        'password_confirmation' => 'umasenhabemforte123',
    ]);
    $register->assertJsonMissingPath('data.password');

    expect($register->getContent())->not->toContain('umasenhabemforte123');

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => 'ada@phpeste.dev',
        'password' => 'umasenhabemforte123',
    ]);
    $login->assertJsonMissingPath('user.password');

    expect($login->getContent())->not->toContain('umasenhabemforte123');

    $token = $login->json('token');
    $me = $this->withToken($token)->getJson('/api/v1/me');
    $me->assertJsonMissingPath('user.password');
});
