<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

$payload = [
    'name' => 'PHPeste 2027',
    'slug' => '2027',
    'starts_on' => '2027-10-08',
    'ends_on' => '2027-10-09',
    'city' => 'João Pessoa',
    'state' => 'PB',
];

it('cria a edição em rascunho e torna quem criou organizador', function () use ($payload): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/communities/{$community->id}/editions", $payload)
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.role', 'organizador')
        ->assertJsonPath('data.year', 2_027)
        ->assertJsonPath('data.timezone', 'America/Fortaleza')
        ->assertJsonPath('data.allowed_transitions', ['cfp_open', 'sales_open']);

    $this->assertDatabaseHas('edition_members', [
        'edition_id' => $response->json('data.id'),
        'user_id' => $user->id,
        'role' => 'organizador',
    ]);
});

it('não deixa quem não gerencia a comunidade criar edição nela', function () use ($payload): void {
    $community = Community::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/communities/{$community->id}/editions", $payload)->assertForbidden();

    $this->assertDatabaseCount('editions', 0);
});

it('exige slug único dentro da comunidade, mas permite o mesmo slug em outra', function () use ($payload): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    $other = Community::factory()->createdBy($user->id)->create();
    Edition::factory()->for($community)->create(['slug' => '2027']);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/communities/{$community->id}/editions", $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);

    $this->postJson("/api/v1/communities/{$other->id}/editions", $payload)->assertCreated();
});

it('rejeita data de término antes do início e UF inexistente', function () use ($payload): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/communities/{$community->id}/editions", [
        ...$payload,
        'ends_on' => '2027-10-01',
        'state' => 'XX',
        'timezone' => 'Lua/Crateras',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['ends_on', 'state', 'timezone']);
});

it('lista as edições da comunidade para quem a gerencia', function (): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    Edition::factory()->for($community)->count(2)->create();
    Edition::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/communities/{$community->id}/editions")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lista as edições em que a pessoa tem papel, no formato do seletor do painel', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create(['name' => 'PHPeste 2027', 'slug' => '2027', 'starts_on' => '2027-10-08']);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Reviewer]);
    Edition::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/editions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $edition->id)
        ->assertJsonPath('data.0.role', 'avaliador')
        ->assertJsonPath('data.0.year', 2_027)
        ->assertJsonPath('data.0.city', 'João Pessoa')
        ->assertJsonPath('data.0.state', 'PB')
        ->assertJsonPath('data.0.site_url', null)
        ->assertJsonPath('data.0.community.id', $edition->community_id)
        ->assertJsonPath('data.0.community.name', $edition->community->name);
});

it('mostra a edição para qualquer membro, com o papel dele', function (EditionRole $role): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/editions/{$edition->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $edition->id)
        ->assertJsonPath('data.role', $role->value);
})->with(EditionRole::cases());

it('deixa só o organizador editar a edição', function (EditionRole $role, int $status): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create(['city' => 'Recife']);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['city' => 'João Pessoa'])->assertStatus($status);
})->with([
    'organizador' => [EditionRole::Organizer, 200],
    'financeiro' => [EditionRole::Finance, 403],
    'avaliador' => [EditionRole::Reviewer, 403],
    'check-in' => [EditionRole::CheckIn, 403],
    'voluntário' => [EditionRole::Volunteer, 403],
]);

it('permite trocar o slug enquanto a edição está em rascunho', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create(['slug' => '2027']);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['slug' => 'phpeste-2027'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'phpeste-2027');
});

it('não permite trocar o slug depois que a edição sai de rascunho', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->status(EditionStatus::CfpOpen)->create(['slug' => '2027']);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['slug' => 'outro'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['slug' => '2027', 'name' => 'PHPeste 2027 — João Pessoa'])
        ->assertOk();

    expect($edition->fresh()?->slug)->toBe('2027');
});

it('valida a data de término contra a data de início já gravada', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create(['starts_on' => '2027-10-08', 'ends_on' => '2027-10-09']);
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['ends_on' => '2027-10-01'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['ends_on']);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['starts_on' => '2027-10-20'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['starts_on']);

    $this->patchJson("/api/v1/editions/{$edition->id}", ['ends_on' => '2027-10-10'])->assertOk();
});

it('remove uma edição em rascunho', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/editions/{$edition->id}")->assertNoContent();

    $this->assertDatabaseMissing('editions', ['id' => $edition->id]);
    $this->assertDatabaseMissing('edition_members', ['edition_id' => $edition->id]);
});

it('não remove uma edição já publicada', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/editions/{$edition->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);

    $this->assertDatabaseHas('editions', ['id' => $edition->id]);
});

it('responde 404 para edição inexistente ou id que não é UUID', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/editions/'.Str::uuid())->assertNotFound();
    $this->getJson('/api/v1/editions/123')->assertNotFound();
});
