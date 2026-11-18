<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->travelTo('2027-02-01 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/cfp/settings";
    Sanctum::actingAs($this->organizer);
});

it('começa em preparação com as regras padrão', function (): void {
    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.anonymous_review', true)
        ->assertJsonPath('data.require_nordeste_quota', true)
        ->assertJsonPath('data.nordeste_quota_percent', 50)
        ->assertJsonPath('data.proposals_per_person', 3);
});

it('salva a janela e as regras e calcula o status', function (): void {
    $this->patchJson($this->url, [
        'opens_at' => '2027-01-15T09:00:00-03:00',
        'closes_at' => '2027-04-15T23:59:00-03:00',
        'results_at' => '2027-05-10T12:00:00-03:00',
        'nordeste_quota_percent' => 60,
        'proposals_per_person' => 2,
        'anonymous_review' => false,
    ])->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.status_label', 'CFP aberto')
        ->assertJsonPath('data.opens_at', '2027-01-15T12:00:00Z')
        ->assertJsonPath('data.days_until_close', 74)
        ->assertJsonPath('data.nordeste_quota_percent', 60)
        ->assertJsonPath('data.anonymous_review', false);
});

it('rejeita janela inválida', function (): void {
    $this->patchJson($this->url, ['opens_at' => '2027-04-15T00:00:00Z', 'closes_at' => '2027-03-01T00:00:00Z'])
        ->assertStatus(422)
        ->assertJsonPath('errors.closes_at.0', 'O fechamento precisa ser depois da abertura.');

    $this->patchJson($this->url, ['opens_at' => '2027-03-01T00:00:00Z', 'closes_at' => '2027-04-15T00:00:00Z', 'results_at' => '2027-04-01T00:00:00Z'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['results_at']);

    $this->patchJson($this->url, ['opens_at' => '2027-03-01T00:00:00Z', 'closes_at' => '2027-04-15T00:00:00Z'])->assertOk();
    $this->patchJson($this->url, ['closes_at' => '2027-02-15T00:00:00Z'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['closes_at']);

    $this->patchJson($this->url, ['nordeste_quota_percent' => 120, 'proposals_per_person' => 0])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['nordeste_quota_percent', 'proposals_per_person']);
});

it('revisor vê a configuração, mas só a organização altera', function (): void {
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);

    $this->getJson($this->url)->assertOk();
    $this->getJson("/api/v1/editions/{$this->edition->id}/cfp/criteria")->assertOk();
    $this->patchJson($this->url, ['proposals_per_person' => 10])->assertForbidden();
    $this->postJson("/api/v1/editions/{$this->edition->id}/cfp/tracks", ['name' => 'X', 'slots' => 1])->assertForbidden();
    $this->postJson("/api/v1/editions/{$this->edition->id}/cfp/criteria", ['name' => 'X', 'weight' => 1])->assertForbidden();
});

it('quem não avalia nem organiza não vê a configuração', function (EditionRole $role): void {
    $user = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertForbidden();
    $this->patchJson($this->url, ['proposals_per_person' => 10])->assertForbidden();
})->with([EditionRole::Finance, EditionRole::CheckIn, EditionRole::Volunteer]);
