<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Events\ThemePublished;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/theme";
    $this->publicUrl = "/api/v1/public/editions/{$this->edition->id}/theme";
    Sanctum::actingAs($this->organizer);
});

it('publica o rascunho e dispara ThemePublished', function (): void {
    Event::fake([ThemePublished::class]);

    $this->postJson("{$this->url}/draft/preset", ['preset' => 'mare']);

    $this->postJson("{$this->url}/publish")
        ->assertOk()
        ->assertJsonPath('data.published.colors.bg', '#0F1B2D')
        ->assertJsonPath('data.has_unpublished_changes', false)
        ->assertJsonPath('data.published_at', fn (?string $date): bool => $date !== null);

    Event::assertDispatched(ThemePublished::class, fn (ThemePublished $event): bool => $event->editionId === $this->edition->id);
});

it('publicar invalida o cache do endpoint público', function (): void {
    $this->getJson($this->publicUrl)->assertJsonPath('data.theme.colors.bg', '#FBF4E4');

    $this->postJson("{$this->url}/draft/preset", ['preset' => 'brasa']);
    $this->getJson($this->publicUrl)->assertJsonPath('data.theme.colors.bg', '#FBF4E4');

    $this->postJson("{$this->url}/publish")->assertOk();
    $this->getJson($this->publicUrl)->assertJsonPath('data.theme.colors.bg', '#12100E');
});

it('reverte para a identidade publicada antes e invalida o cache', function (): void {
    $this->postJson("{$this->url}/draft/preset", ['preset' => 'mare']);
    $this->postJson("{$this->url}/publish");
    $this->postJson("{$this->url}/draft/preset", ['preset' => 'brasa']);
    $this->postJson("{$this->url}/publish")->assertJsonPath('data.can_revert', true);
    $this->getJson($this->publicUrl)->assertJsonPath('data.theme.colors.bg', '#12100E');

    $this->postJson("{$this->url}/revert")
        ->assertOk()
        ->assertJsonPath('data.published.colors.bg', '#0F1B2D')
        ->assertJsonPath('data.draft.colors.bg', '#0F1B2D')
        ->assertJsonPath('data.can_revert', false);

    $this->getJson($this->publicUrl)->assertJsonPath('data.theme.colors.bg', '#0F1B2D');
});

it('não reverte quando não há publicação anterior', function (): void {
    $this->postJson("{$this->url}/publish");

    $this->postJson("{$this->url}/revert")
        ->assertStatus(422)
        ->assertJsonPath('errors.theme.0', 'Não há identidade publicada anterior para restaurar.');
});
