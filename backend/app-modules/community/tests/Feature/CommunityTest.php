<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

it('cria uma comunidade e registra quem criou', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/communities', ['name' => 'PHP-PB', 'slug' => 'php-pb'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'PHP-PB')
        ->assertJsonPath('data.slug', 'php-pb');

    $this->assertDatabaseHas('communities', ['slug' => 'php-pb', 'created_by' => $user->id]);
});

it('exige e-mail verificado para criar comunidade', function (): void {
    Sanctum::actingAs(User::factory()->unverified()->create());

    $this->postJson('/api/v1/communities', ['name' => 'PHP-PB', 'slug' => 'php-pb'])
        ->assertForbidden();

    $this->assertDatabaseCount('communities', 0);
});

it('exige autenticação', function (): void {
    $this->postJson('/api/v1/communities', ['name' => 'PHP-PB', 'slug' => 'php-pb'])
        ->assertUnauthorized();
});

it('rejeita slug inválido ou repetido', function (string $slug): void {
    Community::factory()->create(['slug' => 'php-pb']);
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/communities', ['name' => 'PHP-PB', 'slug' => $slug])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['slug']);
})->with(['php-pb', 'PHP PB', 'php_pb', '-php']);

it('lista só as comunidades que a pessoa criou ou organiza', function (): void {
    $user = User::factory()->create();
    $created = Community::factory()->createdBy($user->id)->create(['name' => 'A criada']);
    $organized = Edition::factory()->create();
    EditionMember::factory()->for($organized)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    $volunteered = Edition::factory()->create();
    EditionMember::factory()->for($volunteered)->create(['user_id' => $user->id, 'role' => EditionRole::Volunteer]);
    Community::factory()->create();

    Sanctum::actingAs($user);

    $ids = collect($this->getJson('/api/v1/communities')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toHaveCount(2)
        ->toContain($created->id, $organized->community_id)
        ->not->toContain($volunteered->community_id);
});

it('deixa quem criou ou organiza ver e editar a comunidade', function (): void {
    $creator = User::factory()->create();
    $community = Community::factory()->createdBy($creator->id)->create();
    $organizer = User::factory()->create();
    EditionMember::factory()
        ->for(Edition::factory()->for($community))
        ->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);

    foreach ([$creator, $organizer] as $user) {
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/communities/{$community->id}")->assertOk();
        $this->patchJson("/api/v1/communities/{$community->id}", ['name' => "Editada por {$user->name}"])
            ->assertOk()
            ->assertJsonPath('data.name', "Editada por {$user->name}");
    }
});

it('nega acesso à comunidade para quem não a gerencia', function (EditionRole $role): void {
    $community = Community::factory()->create();
    $user = User::factory()->create();
    EditionMember::factory()
        ->for(Edition::factory()->for($community))
        ->create(['user_id' => $user->id, 'role' => $role]);

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/communities/{$community->id}")->assertForbidden();
    $this->patchJson("/api/v1/communities/{$community->id}", ['name' => 'Invasão'])->assertForbidden();
    $this->deleteJson("/api/v1/communities/{$community->id}")->assertForbidden();

    expect($community->fresh()?->name)->not->toBe('Invasão');
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);

it('remove uma comunidade sem edições', function (): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/communities/{$community->id}")->assertNoContent();

    $this->assertDatabaseMissing('communities', ['id' => $community->id]);
});

it('não remove uma comunidade que já tem edições', function (): void {
    $user = User::factory()->create();
    $community = Community::factory()->createdBy($user->id)->create();
    Edition::factory()->for($community)->create();
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/communities/{$community->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['community']);

    $this->assertDatabaseHas('communities', ['id' => $community->id]);
});
