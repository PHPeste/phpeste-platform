<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Community\Tests\Fixtures\EditionScopedNote;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Schema::create('edition_scoped_notes', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->foreignUuid('edition_id')->constrained('editions');
        $table->string('body');
        $table->timestamps();
    });

    $this->editionA = Edition::factory()->create();
    $this->editionB = Edition::factory()->create();
    $this->organizerOfA = User::factory()->create();
    EditionMember::factory()->for($this->editionA)->create(['user_id' => $this->organizerOfA->id, 'role' => EditionRole::Organizer]);
});

it('nega a quem é da edição A qualquer acesso à edição B', function (): void {
    Sanctum::actingAs($this->organizerOfA);
    $editionB = $this->editionB;

    $this->getJson("/api/v1/editions/{$editionB->id}")->assertForbidden();
    $this->patchJson("/api/v1/editions/{$editionB->id}", ['name' => 'Invasão'])->assertForbidden();
    $this->postJson("/api/v1/editions/{$editionB->id}/status", ['status' => 'cfp_open'])->assertForbidden();
    $this->deleteJson("/api/v1/editions/{$editionB->id}")->assertForbidden();
    $this->getJson("/api/v1/communities/{$editionB->community_id}/editions")->assertForbidden();

    $ids = collect($this->getJson('/api/v1/editions')->json('data'))->pluck('id');

    expect($ids)->toContain($this->editionA->id)->not->toContain($editionB->id);
    expect($editionB->fresh()?->name)->not->toBe('Invasão');
});

it('filtra o recurso de edição pela edição do contexto', function (): void {
    EditionScopedNote::create(['edition_id' => $this->editionA->id, 'body' => 'da A']);
    EditionScopedNote::create(['edition_id' => $this->editionB->id, 'body' => 'da B']);

    $bodies = resolve(CurrentEdition::class)->run(
        $this->editionA->id,
        fn (): array => EditionScopedNote::query()->pluck('body')->all(),
    );

    expect($bodies)->toBe(['da A']);
});

it('não devolve nada quando não há edição no contexto', function (): void {
    EditionScopedNote::create(['edition_id' => $this->editionA->id, 'body' => 'da A']);

    expect(EditionScopedNote::query()->count())->toBe(0)
        ->and(EditionScopedNote::query()->forEdition($this->editionA->id)->count())->toBe(1);
});

it('preenche edition_id a partir do contexto e recusa criar sem edição', function (): void {
    $note = resolve(CurrentEdition::class)->run(
        $this->editionB->id,
        fn (): EditionScopedNote => EditionScopedNote::create(['body' => 'criada no contexto']),
    );

    expect($note->edition_id)->toBe($this->editionB->id);

    EditionScopedNote::create(['body' => 'sem edição']);
})->throws(LogicException::class);

it('liga a edição da URL só durante a requisição', function (): void {
    Sanctum::actingAs($this->organizerOfA);

    $this->getJson("/api/v1/editions/{$this->editionA->id}")->assertOk();

    expect(resolve(CurrentEdition::class)->id())->toBeNull();
});
