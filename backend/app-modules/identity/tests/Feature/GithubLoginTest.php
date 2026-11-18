<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GithubUser;
use PHPeste\Identity\Actions\GithubState;
use PHPeste\Identity\Models\SocialAccount;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    config([
        'services.github.client_id' => 'cliente-teste',
        'services.github.client_secret' => 'segredo-teste',
        'services.github.redirect' => 'https://admin.phpeste.dev/login/github',
    ]);
});

function fakeGithub(array $attributes = []): void
{
    Socialite::fake('github', GithubUser::fake([
        'id' => '4242',
        'nickname' => 'adalovelace',
        'name' => 'Ada Lovelace',
        'email' => 'ada@phpeste.dev',
        'avatar' => 'https://avatars.githubusercontent.com/u/4242',
        ...$attributes,
    ]));
}

function githubCallback(object $test, string $intent = GithubState::LOGIN, ?string $userId = null): TestResponse
{
    return $test->postJson('/api/v1/auth/github/callback', ['code' => 'codigo-do-github', 'state' => GithubState::issue($intent, $userId)]);
}

it('devolve a URL de autorização do GitHub com state assinado e escopo de e-mail', function (): void {
    $url = $this->getJson('/api/v1/auth/github/redirect')->assertOk()->json('url');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('https://github.com/login/oauth/authorize')
        ->and($query['client_id'])->toBe('cliente-teste')
        ->and($query['redirect_uri'])->toBe('https://admin.phpeste.dev/login/github')
        ->and($query['scope'])->toContain('user:email')
        ->and(GithubState::decode($query['state'])['intent'])->toBe(GithubState::LOGIN);
});

it('cria a conta verificada, sem senha, no primeiro login com GitHub', function (): void {
    fakeGithub();

    githubCallback($this)
        ->assertOk()
        ->assertJsonPath('user.email', 'ada@phpeste.dev')
        ->assertJsonPath('user.has_password', false)
        ->assertJsonPath('user.providers', ['github'])
        ->assertJsonStructure(['token']);

    $user = User::where('email', 'ada@phpeste.dev')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull();
    $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '4242', 'nickname' => 'adalovelace']);
});

it('nunca duplica conta: vincula pelo e-mail verificado a uma conta que já existe', function (): void {
    $existing = User::factory()->unverified()->create(['email' => 'Ada@PHPeste.dev', 'password' => 'senhaforte123456']);
    fakeGithub();

    githubCallback($this)->assertOk()->assertJsonPath('user.id', $existing->id)->assertJsonPath('user.has_password', true);

    expect(User::query()->count())->toBe(1)
        ->and($existing->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('reconhece a conta já vinculada pelo id do GitHub, mesmo que o e-mail mude lá', function (): void {
    $user = User::factory()->create(['email' => 'ada@phpeste.dev']);
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '4242']);
    fakeGithub(['email' => 'ada.nova@example.com']);

    githubCallback($this)->assertOk()->assertJsonPath('user.id', $user->id);

    expect(User::query()->count())->toBe(1);
});

it('recusa GitHub sem e-mail principal verificado', function (): void {
    fakeGithub(['email' => null]);

    githubCallback($this)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['github']);

    $this->assertDatabaseCount('users', 0);
});

it('recusa state adulterado ou expirado', function (): void {
    fakeGithub();

    $this->postJson('/api/v1/auth/github/callback', ['code' => 'x', 'state' => 'inventado'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['state']);

    $state = GithubState::issue(GithubState::LOGIN);
    $this->travel(11)->minutes();

    $this->postJson('/api/v1/auth/github/callback', ['code' => 'x', 'state' => $state])->assertStatus(422);
});

it('conta criada pelo GitHub não entra com senha nenhuma', function (): void {
    User::factory()->create(['email' => 'ada@phpeste.dev', 'password' => null]);

    $this->postJson('/api/v1/auth/login', ['email' => 'ada@phpeste.dev', 'password' => ''])->assertStatus(422);
    $this->postJson('/api/v1/auth/login', ['email' => 'ada@phpeste.dev', 'password' => 'qualquercoisa'])->assertStatus(401);
});

it('conecta o GitHub à conta logada', function (): void {
    $user = User::factory()->create(['email' => 'rita@phpeste.dev']);
    Sanctum::actingAs($user);

    $url = $this->getJson('/api/v1/auth/github/link')->assertOk()->json('url');
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    fakeGithub();

    expect(GithubState::decode($query['state']))->toBe(['intent' => GithubState::LINK, 'user' => $user->id]);

    $this->postJson('/api/v1/auth/github/callback', ['code' => 'x', 'state' => $query['state']])
        ->assertOk()
        ->assertJsonPath('user.providers', ['github'])
        ->assertJsonMissingPath('token');

    expect(User::query()->count())->toBe(1);
});

it('não conecta com state de outra pessoa nem GitHub que já é de outra conta', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    SocialAccount::create(['user_id' => $other->id, 'provider' => 'github', 'provider_user_id' => '4242']);
    fakeGithub();
    Sanctum::actingAs($user);

    githubCallback($this, GithubState::LINK, $other->id)->assertForbidden();

    githubCallback($this, GithubState::LINK, $user->id)
        ->assertStatus(422)
        ->assertJsonPath('errors.github.0', 'Esta conta do GitHub já está conectada a outro usuário.');
});

it('desconecta o GitHub só se ainda houver outro jeito de entrar', function (): void {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'github', 'provider_user_id' => '4242']);
    $token = $user->createToken('pest')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/auth/github')
        ->assertStatus(422)
        ->assertJsonPath('errors.github.0', 'Defina uma senha antes de desconectar o GitHub, senão você perde o acesso à conta.');

    $this->withToken($token)->putJson('/api/v1/auth/password', ['password' => 'senhanovaforte456', 'password_confirmation' => 'senhanovaforte456'])->assertOk();

    $this->withToken($token)->deleteJson('/api/v1/auth/github')
        ->assertOk()
        ->assertJsonPath('user.providers', []);

    $this->assertDatabaseCount('social_accounts', 0);
});
