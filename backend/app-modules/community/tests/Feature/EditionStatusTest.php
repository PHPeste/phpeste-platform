<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Events\EditionStatusChanged;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Event::fake([EditionStatusChanged::class]);
});

it('leva a edição por todo o ciclo e dispara EditionStatusChanged a cada passo', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $steps = [
        [EditionStatus::Draft, EditionStatus::CfpOpen],
        [EditionStatus::CfpOpen, EditionStatus::SalesOpen],
        [EditionStatus::SalesOpen, EditionStatus::Ongoing],
        [EditionStatus::Ongoing, EditionStatus::Closed],
    ];

    foreach ($steps as [$from, $to]) {
        $this->postJson("/api/v1/editions/{$edition->id}/status", ['status' => $to->value])
            ->assertOk()
            ->assertJsonPath('data.status', $to->value);

        Event::assertDispatched(
            EditionStatusChanged::class,
            fn (EditionStatusChanged $event): bool => $event->editionId === $edition->id && $event->from === $from && $event->to === $to,
        );
    }

    Event::assertDispatchedTimes(EditionStatusChanged::class, 4);
    expect($edition->fresh()?->status)->toBe(EditionStatus::Closed);
});

it('rejeita transição inválida com mensagem clara e sem disparar evento', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/editions/{$edition->id}/status", ['status' => 'closed'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('errors.status.0', 'Não é possível passar a edição de "Rascunho" para "Encerrada".');

    expect($edition->fresh()?->status)->toBe(EditionStatus::Draft);
    Event::assertNotDispatched(EditionStatusChanged::class);
});

it('rejeita status desconhecido', function (): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/editions/{$edition->id}/status", ['status' => 'publicada'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status']);
});

it('deixa só o organizador mudar o status', function (EditionRole $role): void {
    $user = User::factory()->create();
    $edition = Edition::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/editions/{$edition->id}/status", ['status' => 'cfp_open'])->assertForbidden();

    expect($edition->fresh()?->status)->toBe(EditionStatus::Draft);
    Event::assertNotDispatched(EditionStatusChanged::class);
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);
