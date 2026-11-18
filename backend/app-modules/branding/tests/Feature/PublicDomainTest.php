<?php

declare(strict_types=1);

use PHPeste\Branding\Domains\DomainResolver;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create(['name' => 'PHPeste 2027']);
});

function domainFor(Edition $edition, string $host, bool $verified = true, bool $primary = false): EditionDomain
{
    return EditionDomain::create([
        'edition_id' => $edition->id,
        'host' => $host,
        'is_primary' => $primary,
        'verification_token' => 'x',
        'verified_at' => $verified ? now() : null,
    ]);
}

it('resolve a edição pelo host verificado, sem login, com cache curto', function (): void {
    domainFor($this->edition, '2027.phpeste.dev', primary: true);
    domainFor($this->edition, 'phpeste.com.br');

    $this->getJson('/api/v1/public/resolve?host=PHPeste.com.br:443')
        ->assertOk()
        ->assertJsonPath('data.edition_id', $this->edition->id)
        ->assertJsonPath('data.edition_name', 'PHPeste 2027')
        ->assertJsonPath('data.host', 'phpeste.com.br')
        ->assertJsonPath('data.primary_host', '2027.phpeste.dev')
        ->assertHeader('Cache-Control', 'max-age=60, public');
});

it('host não verificado nunca resolve', function (): void {
    domainFor($this->edition, 'phpeste.com.br', verified: false);

    $this->getJson('/api/v1/public/resolve?host=phpeste.com.br')->assertNotFound();
});

it('não resolve edição em rascunho, host desconhecido nem host malformado', function (): void {
    domainFor(Edition::factory()->create(), 'rascunho.phpeste.dev');

    $this->getJson('/api/v1/public/resolve?host=rascunho.phpeste.dev')->assertNotFound();
    $this->getJson('/api/v1/public/resolve?host=nada.phpeste.dev')->assertNotFound();
    $this->getJson('/api/v1/public/resolve?host=%3Cscript%3E')->assertNotFound();
    $this->getJson('/api/v1/public/resolve')->assertNotFound();
});

it('passa a resolver logo depois da verificação, sem esperar o cache expirar', function (): void {
    $domain = domainFor($this->edition, 'phpeste.com.br', verified: false);

    $this->getJson('/api/v1/public/resolve?host=phpeste.com.br')->assertNotFound();

    $domain->update(['verified_at' => now()]);
    DomainResolver::forget('phpeste.com.br');

    $this->getJson('/api/v1/public/resolve?host=phpeste.com.br')->assertOk();
});

it('autoriza certificado TLS só para host verificado de edição publicada', function (): void {
    domainFor($this->edition, 'phpeste.com.br');
    domainFor($this->edition, 'pendente.com.br', verified: false);

    $this->get('/api/v1/public/tls-check?domain=phpeste.com.br')->assertOk();
    $this->get('/api/v1/public/tls-check?domain=pendente.com.br')->assertNotFound();
    $this->get('/api/v1/public/tls-check?domain=qualquer.com')->assertNotFound();
});
