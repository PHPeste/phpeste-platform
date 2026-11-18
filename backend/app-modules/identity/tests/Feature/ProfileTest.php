<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Storage::fake('public');
    config(['filesystems.media' => 'public']);
    $this->user = User::factory()->create(['name' => 'Ada']);
    Sanctum::actingAs($this->user);
});

it('atualiza nome e idioma do perfil global', function (): void {
    $this->patchJson('/api/v1/me', ['name' => 'Ada Lovelace', 'locale' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.locale', 'en');

    $this->patchJson('/api/v1/me', ['locale' => 'klingon'])->assertStatus(422)->assertJsonValidationErrors(['locale']);
});

it('recebe a foto, corta em quadrado de 800 px e regrava em JPEG', function (): void {
    $response = $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('eu.png', 1_200, 900)])
        ->assertOk()
        ->assertJsonPath('data.avatar_url', fn (?string $url): bool => $url !== null && str_ends_with($url, '.jpg'));

    $path = (string) $this->user->refresh()->photo_path;
    $size = getimagesizefromstring((string) Storage::disk('public')->get($path));

    expect($path)->toStartWith("users/{$this->user->id}/photo-")
        ->and($size[0])->toBe(800)
        ->and($size[1])->toBe(800)
        ->and($size[2])->toBe(IMAGETYPE_JPEG);

    $this->getJson('/api/v1/me')->assertJsonPath('user.avatar_url', $response->json('data.avatar_url'));
});

it('rejeita arquivo que não é imagem de verdade, mesmo com extensão de imagem', function (): void {
    $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->createWithContent('foto.jpg', '<?php system($_GET["c"]); ?>')])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['photo']);

    $gif = UploadedFile::fake()->createWithContent('foto.png', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    $this->postJson('/api/v1/me/photo', ['photo' => $gif])->assertStatus(422);

    expect($this->user->refresh()->photo_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('exige foto de pelo menos 600 px', function (): void {
    $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('eu.jpg', 800, 500)])
        ->assertStatus(422)
        ->assertJsonPath('errors.photo.0', 'A foto precisa ter pelo menos 600 × 600 px.');
});

it('troca a foto apagando a anterior e remove a foto', function (): void {
    $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('a.jpg', 700, 700)]);
    $first = (string) $this->user->refresh()->photo_path;

    $this->postJson('/api/v1/me/photo', ['photo' => UploadedFile::fake()->image('b.jpg', 700, 700)]);
    Storage::disk('public')->assertMissing($first);

    $this->deleteJson('/api/v1/me/photo')->assertOk()->assertJsonPath('data.avatar_url', null);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});
