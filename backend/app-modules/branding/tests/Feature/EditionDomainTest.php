<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Contracts\DnsResolver;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

class FakeDns implements DnsResolver
{
    /** @var array<string, list<string>> */
    public array $txt = [];

    /** @var array<string, list<string>> */
    public array $cname = [];

    public function txt(string $host): array
    {
        return $this->txt[$host] ?? [];
    }

    public function cname(string $host): array
    {
        return $this->cname[$host] ?? [];
    }
}

beforeEach(function (): void {
    config(['app.public_base_domain' => 'phpeste.dev', 'app.public_cname_target' => 'edicoes.phpeste.dev']);
    $this->dns = new FakeDns;
    $this->app->instance(DnsResolver::class, $this->dns);
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/domains";
    Sanctum::actingAs($this->organizer);
});

it('subdomínio da plataforma entra verificado e vira o principal', function (): void {
    $this->postJson($this->url, ['host' => 'https://2027-PHP-PB.phpeste.dev/'])
        ->assertCreated()
        ->assertJsonPath('data.host', '2027-php-pb.phpeste.dev')
        ->assertJsonPath('data.is_platform', true)
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.verification', null);
});

it('recusa subdomínio reservado ou com mais de um nível na plataforma', function (string $host): void {
    $this->postJson($this->url, ['host' => $host])->assertStatus(422)->assertJsonValidationErrors(['host']);
})->with(['admin.phpeste.dev', 'www.phpeste.dev', 'a.b.phpeste.dev']);

it('domínio próprio fica pendente com as instruções de DNS', function (): void {
    $this->postJson($this->url, ['host' => 'phpeste.com.br'])
        ->assertCreated()
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.verification.txt.name', '_phpeste-verify.phpeste.com.br')
        ->assertJsonPath('data.verification.txt.value', fn (string $value): bool => str_starts_with($value, 'phpeste-verify='))
        ->assertJsonPath('data.verification.cname.value', 'edicoes.phpeste.dev')
        ->assertJsonMissingPath('data.verification_token');
});

it('verifica domínio próprio pelo TXT', function (): void {
    $this->postJson($this->url, ['host' => 'phpeste.com.br']);
    $domain = $this->getJson($this->url)->json('data.0');

    $this->postJson("{$this->url}/{$domain['id']}/verify")
        ->assertOk()
        ->assertJsonPath('data.verified', false)
        ->assertJsonPath('data.last_check_error', fn (string $error): bool => str_contains($error, '_phpeste-verify.phpeste.com.br'));

    $this->dns->txt['_phpeste-verify.phpeste.com.br'] = ['v=spf1 -all', $domain['verification']['txt']['value']];

    $this->postJson("{$this->url}/{$domain['id']}/verify")
        ->assertOk()
        ->assertJsonPath('data.verified', true)
        ->assertJsonPath('data.last_check_error', null);
});

it('verifica domínio próprio pelo CNAME', function (): void {
    $id = $this->postJson($this->url, ['host' => 'evento.phpce.org'])->json('data.id');
    $this->dns->cname['evento.phpce.org'] = ['edicoes.phpeste.dev'];

    $this->postJson("{$this->url}/{$id}/verify")->assertOk()->assertJsonPath('data.verified', true);
});

it('impede dois cadastros do mesmo host, inclusive em outra edição', function (): void {
    $this->postJson($this->url, ['host' => 'phpeste.com.br'])->assertCreated();
    $this->postJson($this->url, ['host' => 'PHPESTE.com.br.'])->assertStatus(422)->assertJsonPath('errors.host.0', 'Este domínio já está cadastrado.');

    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->postJson("/api/v1/editions/{$other->id}/domains", ['host' => 'phpeste.com.br'])->assertStatus(422);
});

it('rejeita host inválido', function (): void {
    $this->postJson($this->url, ['host' => 'não é domínio'])->assertStatus(422)->assertJsonValidationErrors(['host']);
});

it('troca o domínio principal só para um verificado e promove outro ao remover o principal', function (): void {
    $platform = $this->postJson($this->url, ['host' => '2027.phpeste.dev'])->json('data.id');
    $custom = $this->postJson($this->url, ['host' => 'phpeste.com.br'])->json('data.id');

    $this->postJson("{$this->url}/{$custom}/primary")->assertStatus(422);

    $this->dns->cname['phpeste.com.br'] = ['edicoes.phpeste.dev'];
    $this->postJson("{$this->url}/{$custom}/verify");
    $this->postJson("{$this->url}/{$custom}/primary")->assertOk()->assertJsonPath('data.is_primary', true);

    $this->getJson($this->url)->assertJsonPath('data.0.id', $custom)->assertJsonPath('data.1.is_primary', false);

    $this->deleteJson("{$this->url}/{$custom}")->assertNoContent();
    $this->getJson($this->url)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $platform)->assertJsonPath('data.0.is_primary', true);
});

it('deixa só quem configura a edição mexer nos domínios', function (EditionRole $role): void {
    $user = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertForbidden();
    $this->postJson($this->url, ['host' => '2027.phpeste.dev'])->assertForbidden();
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);
