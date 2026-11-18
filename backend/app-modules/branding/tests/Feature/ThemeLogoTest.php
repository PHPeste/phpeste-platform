<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Storage::fake('public');
    $this->edition = Edition::factory()->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/theme/logos";
    Sanctum::actingAs($this->organizer);
});

function storedPath(?string $url): string
{
    return (string) str((string) $url)->after('/storage/');
}

it('envia o logo principal em PNG e coloca no rascunho', function (): void {
    $response = $this->postJson("{$this->url}/primary", ['file' => UploadedFile::fake()->image('logo.png', 600, 200)])
        ->assertOk()
        ->assertJsonPath('data.published', null);

    $path = storedPath($response->json('data.draft.logos.primary'));

    expect($path)->toStartWith("branding/{$this->edition->id}/primary-")->toEndWith('.png');
    Storage::disk('public')->assertExists($path);
});

it('remove scripts e handlers de um SVG enviado', function (): void {
    $svg = <<<'SVG'
        <?xml version="1.0"?>
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)" viewBox="0 0 10 10">
          <script>alert(document.cookie)</script>
          <rect width="10" height="10" fill="#6B2A12" onclick="steal()"/>
          <a xlink:href="javascript:alert(1)"><circle r="2"/></a>
          <image href="https://evil.example/track.png"/>
        </svg>
        SVG;

    $response = $this->postJson("{$this->url}/dark", ['file' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])
        ->assertOk();

    $stored = (string) Storage::disk('public')->get(storedPath($response->json('data.draft.logos.dark')));

    expect($stored)
        ->toContain('<rect')
        ->not->toContain('<script')
        ->not->toContain('onload')
        ->not->toContain('onclick')
        ->not->toContain('javascript:')
        ->not->toContain('evil.example');
});

it('rejeita SVG que não é SVG de verdade', function (): void {
    $this->postJson("{$this->url}/primary", ['file' => UploadedFile::fake()->createWithContent('logo.svg', 'isto não é xml')])
        ->assertStatus(422);
});

it('exige PNG quadrado de pelo menos 512 px no ícone do app', function (): void {
    $this->postJson("{$this->url}/app_icon", ['file' => UploadedFile::fake()->image('icon.png', 256, 256)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $this->postJson("{$this->url}/app_icon", ['file' => UploadedFile::fake()->image('icon.png', 600, 512)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $this->postJson("{$this->url}/app_icon", ['file' => UploadedFile::fake()->image('icon.png', 512, 512)])
        ->assertOk();
});

it('rejeita arquivo que não é imagem e slot desconhecido', function (): void {
    $this->postJson("{$this->url}/favicon", ['file' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);

    $this->postJson("{$this->url}/banner", ['file' => UploadedFile::fake()->image('logo.png')])
        ->assertNotFound();
});

it('remove um logo do rascunho', function (): void {
    $this->postJson("{$this->url}/favicon", ['file' => UploadedFile::fake()->image('favicon.png', 64, 64)])->assertOk();

    $this->deleteJson("{$this->url}/favicon")
        ->assertOk()
        ->assertJsonPath('data.draft.logos.favicon', null);
});

it('só o organizador envia logo', function (): void {
    $volunteer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $volunteer->id, 'role' => EditionRole::Volunteer]);
    Sanctum::actingAs($volunteer);

    $this->postJson("{$this->url}/primary", ['file' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
});
