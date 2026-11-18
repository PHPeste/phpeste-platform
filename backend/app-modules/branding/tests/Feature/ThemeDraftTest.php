<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Enums\ThemePreset;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Branding\Theme\ThemeTokens;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/theme";
    $this->draft = [
        'colors' => ThemePreset::Sertao->colors(),
        'fonts' => ['heading' => 'Lexend', 'body' => 'Inter'],
        'radius' => 12,
    ];
});

it('começa com o rascunho no preset Sertão e nada publicado', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.draft.colors.brand', '#6B2A12')
        ->assertJsonPath('data.draft.fonts.heading', 'Lexend')
        ->assertJsonPath('data.draft.logos.primary', null)
        ->assertJsonPath('data.published', null)
        ->assertJsonPath('data.can_revert', false)
        ->assertJsonPath('data.has_unpublished_changes', true)
        ->assertJsonPath('data.contrast.0', ['foreground' => 'ink', 'background' => 'bg', 'ratio' => 14.77, 'minimum' => 4.5, 'passes' => true]);

    $this->assertDatabaseCount('edition_settings', 1);
});

it('salva o rascunho com cores, fontes e raio', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->putJson("{$this->url}/draft", [...$this->draft, 'colors' => [...$this->draft['colors'], 'accent' => '#f0c060']])
        ->assertOk()
        ->assertJsonPath('data.draft.colors.accent', '#F0C060')
        ->assertJsonPath('data.draft.fonts.body', 'Inter')
        ->assertJsonPath('data.draft.radius', 12)
        ->assertJsonPath('data.published', null);
});

it('rejeita tema com contraste insuficiente explicando qual par falhou', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->putJson("{$this->url}/draft", [...$this->draft, 'colors' => [...$this->draft['colors'], 'ink' => '#D9C9A8']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['colors'])
        ->assertJsonPath('errors.colors.0', fn (string $message): bool => str_starts_with($message, 'Contraste insuficiente entre Texto (ink) e Fundo (bg): 1,')
            && str_ends_with($message, 'o mínimo é 4,5:1.'));

    $this->getJson($this->url)->assertJsonPath('data.draft.colors.ink', '#3A1607');
});

it('rejeita token faltando, token desconhecido e cor fora do formato', function (): void {
    Sanctum::actingAs($this->organizer);
    $colors = $this->draft['colors'];
    unset($colors['brand']);

    $this->putJson("{$this->url}/draft", [...$this->draft, 'colors' => [...$colors, 'css' => 'body{}', 'accent' => 'amarelo']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['colors', 'colors.brand', 'colors.accent']);
});

it('aceita só fontes da lista segura e raio entre 0 e 24', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->putJson("{$this->url}/draft", [
        ...$this->draft,
        'fonts' => ['heading' => 'Comic Sans MS', 'body' => "Inter'); @import url(evil.css"],
        'radius' => 40,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['fonts.heading', 'fonts.body', 'radius']);
});

it('aplica um preset no rascunho mantendo os logos', function (): void {
    Sanctum::actingAs($this->organizer);
    $this->getJson($this->url);
    EditionSetting::query()->forEdition($this->edition->id)->firstOrFail()->update([
        'theme_draft' => [...ThemeTokens::fromPreset(ThemePreset::Sertao), 'logos' => ['primary' => 'branding/x/logo.svg', 'dark' => null, 'favicon' => null, 'app_icon' => null]],
    ]);

    $this->postJson("{$this->url}/draft/preset", ['preset' => 'mare'])
        ->assertOk()
        ->assertJsonPath('data.draft.colors.bg', '#0F1B2D')
        ->assertJsonPath('data.draft.colors.brand', '#2C78CA')
        ->assertJsonPath('data.draft.logos.primary', fn (?string $url): bool => str_ends_with((string) $url, 'branding/x/logo.svg'));

    $this->postJson("{$this->url}/draft/preset", ['preset' => 'neon'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['preset']);
});

it('descarta o rascunho voltando ao que está publicado', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->url}/draft/preset", ['preset' => 'brasa']);
    $this->postJson("{$this->url}/publish")->assertOk();
    $this->postJson("{$this->url}/draft/preset", ['preset' => 'mare'])
        ->assertJsonPath('data.has_unpublished_changes', true);

    $this->deleteJson("{$this->url}/draft")
        ->assertOk()
        ->assertJsonPath('data.draft.colors.bg', '#12100E')
        ->assertJsonPath('data.has_unpublished_changes', false);
});

it('deixa qualquer membro ver o tema, mas só o organizador mudar', function (EditionRole $role): void {
    $user = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertOk();
    $this->putJson("{$this->url}/draft", $this->draft)->assertForbidden();
    $this->postJson("{$this->url}/draft/preset", ['preset' => 'mare'])->assertForbidden();
    $this->deleteJson("{$this->url}/draft")->assertForbidden();
    $this->postJson("{$this->url}/publish")->assertForbidden();
    $this->postJson("{$this->url}/revert")->assertForbidden();
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);

it('nega o tema a quem não é da edição', function (): void {
    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => ($user = User::factory()->create())->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertForbidden();
    $this->putJson("{$this->url}/draft", $this->draft)->assertForbidden();
});

it('lista os presets com o relatório de contraste', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->getJson('/api/v1/branding/presets')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.id', 'sertao')
        ->assertJsonPath('data.1.label', 'Maré')
        ->assertJsonPath('data.2.theme.colors.brand', '#FF7A1A');
});
