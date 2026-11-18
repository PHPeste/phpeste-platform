<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    CfpSetting::create(['edition_id' => $this->edition->id]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}";
    Sanctum::actingAs($this->organizer);
});

it('passa a edição para site próprio, exige o endereço e registra na auditoria', function (): void {
    $this->patchJson($this->url, ['site_mode' => 'external'])
        ->assertStatus(422)
        ->assertJsonPath('errors.external_site_url.0', 'Informe o endereço do site da comunidade para usar o site próprio.');

    $this->patchJson($this->url, ['external_site_url' => 'http://phpeste.php-pb.net'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['external_site_url']);

    $this->patchJson($this->url, ['site_mode' => 'external', 'external_site_url' => 'https://phpeste.php-pb.net/', 'privacy_contact_email' => 'dpo@php-pb.net', 'anonymize_after_event' => true])
        ->assertOk()
        ->assertJsonPath('data.site_mode', 'external')
        ->assertJsonPath('data.external_site_url', 'https://phpeste.php-pb.net')
        ->assertJsonPath('data.site_url', 'https://phpeste.php-pb.net')
        ->assertJsonPath('data.privacy_contact_email', 'dpo@php-pb.net')
        ->assertJsonPath('data.anonymize_after_event', true);

    $log = AuditLog::query()->where('edition_id', $this->edition->id)->where('action', 'edition.settings_changed')->sole();
    expect($log->before)->toBe(['site_mode' => 'platform', 'external_site_url' => null, 'privacy_contact_email' => null, 'anonymize_after_event' => false])
        ->and($log->after['site_mode'])->toBe('external');

    $this->patchJson($this->url, ['name' => 'PHPeste 2027 · João Pessoa'])->assertOk();
    expect(AuditLog::query()->where('action', 'edition.settings_changed')->count())->toBe(1);
});

it('no site da plataforma, o endereço do site é o domínio principal verificado', function (): void {
    $this->getJson($this->url)->assertJsonPath('data.site_url', null);

    EditionDomain::create(['edition_id' => $this->edition->id, 'host' => '2027.phpeste.dev', 'is_primary' => true, 'verification_token' => 'x', 'verified_at' => now()]);

    $this->getJson($this->url)->assertJsonPath('data.site_url', 'https://2027.phpeste.dev');
});

it('diz o que cada próxima etapa libera e bloqueia', function (): void {
    $this->getJson($this->url)
        ->assertJsonPath('data.status_options.0.status', 'sales_open')
        ->assertJsonPath('data.status_options.0.label', 'Vendas abertas')
        ->assertJsonPath('data.status_options.0.unlocks.1', 'Venda de ingressos, quando houver lotes configurados')
        ->assertJsonPath('data.status_options.0.blocks.1', 'Não dá para voltar para CFP aberto nem para rascunho');
});

function allowedOrigin(object $test, string $url, string $origin): ?string
{
    return $test->getJson($url, ['Origin' => $origin])->headers->get('Access-Control-Allow-Origin');
}

it('libera o CORS da API pública para o site próprio de edição publicada, e só para ele', function (): void {
    $public = "/api/v1/public/editions/{$this->edition->id}/cfp";
    $origin = 'https://phpeste.php-pb.net';

    expect(allowedOrigin($this, $public, $origin))->not->toBe($origin);

    $this->patchJson($this->url, ['site_mode' => 'external', 'external_site_url' => "{$origin}/2027"])->assertOk();

    expect(allowedOrigin($this, $public, $origin))->toBe($origin)
        ->and(allowedOrigin($this, $public, 'https://outro-site.dev'))->not->toBe('https://outro-site.dev')
        ->and(allowedOrigin($this, "{$this->url}/team", $origin))->not->toBe($origin)
        ->and(config('cors.allowed_origins'))->not->toContain($origin);

    $this->patchJson($this->url, ['external_site_url' => 'https://novo.php-pb.net'])->assertOk();

    expect(allowedOrigin($this, $public, $origin))->not->toBe($origin)
        ->and(allowedOrigin($this, $public, 'https://novo.php-pb.net'))->toBe('https://novo.php-pb.net');

    $preflight = $this->call('OPTIONS', $public, [], [], [], ['HTTP_ORIGIN' => 'https://novo.php-pb.net', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET']);
    expect($preflight->headers->get('Access-Control-Allow-Origin'))->toBe('https://novo.php-pb.net');
});

it('não libera o CORS de edição em rascunho', function (): void {
    $draft = Edition::factory()->create(['site_mode' => 'external', 'external_site_url' => 'https://rascunho.dev']);

    expect(allowedOrigin($this, "/api/v1/public/editions/{$this->edition->id}/cfp", 'https://rascunho.dev'))->not->toBe('https://rascunho.dev');
    expect($draft->status)->toBe(EditionStatus::Draft);
});

it('a resolução pelo host diz se a edição usa site próprio', function (): void {
    EditionDomain::create(['edition_id' => $this->edition->id, 'host' => 'cfp.phpeste.php-pb.net', 'is_primary' => true, 'verification_token' => 'x', 'verified_at' => now()]);
    $this->patchJson($this->url, ['site_mode' => 'external', 'external_site_url' => 'https://phpeste.php-pb.net'])->assertOk();

    $this->getJson('/api/v1/public/resolve?host=cfp.phpeste.php-pb.net')
        ->assertOk()
        ->assertJsonPath('data.site_mode', 'external')
        ->assertJsonPath('data.external_site_url', 'https://phpeste.php-pb.net');
});
