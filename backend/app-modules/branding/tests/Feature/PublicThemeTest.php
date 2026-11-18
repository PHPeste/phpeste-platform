<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

it('devolve o tema Sertão para edição publicada que ainda não publicou identidade', function (): void {
    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();

    $this->getJson("/api/v1/public/editions/{$edition->id}/theme")
        ->assertOk()
        ->assertJsonPath('data.edition_id', $edition->id)
        ->assertJsonPath('data.theme.colors.brand', '#6B2A12')
        ->assertJsonPath('data.theme.logos.primary', null)
        ->assertJsonPath('data.published_at', null);
});

it('não expõe tema de edição em rascunho, inexistente ou com id inválido', function (): void {
    $draft = Edition::factory()->create();

    $this->getJson("/api/v1/public/editions/{$draft->id}/theme")->assertNotFound();
    $this->getJson('/api/v1/public/editions/'.Str::uuid().'/theme')->assertNotFound();
    $this->getJson('/api/v1/public/editions/2027/theme')->assertNotFound();
});

it('responde com ETag e cache público, e 304 quando o tema não mudou', function (): void {
    $edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $url = "/api/v1/public/editions/{$edition->id}/theme";

    $response = $this->getJson($url)->assertOk();
    $etag = $response->headers->get('ETag');

    expect($etag)->not->toBeNull()
        ->and($response->headers->get('Cache-Control'))->toContain('public', 'max-age=300');

    $this->withHeader('If-None-Match', (string) $etag)->getJson($url)->assertStatus(304);
    $this->withHeader('If-None-Match', '"outro"')->getJson($url)->assertOk();
});
