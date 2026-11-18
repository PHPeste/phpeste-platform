<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionInvitation;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->create();
    $this->organizer = User::factory()->create(['name' => 'Rita Castro', 'email' => 'rita@example.com']);
    $this->organizerMember = EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->reviewer = User::factory()->create(['name' => 'João Paulo']);
    $this->reviewerMember = EditionMember::factory()->for($this->edition)->create(['user_id' => $this->reviewer->id, 'role' => EditionRole::Reviewer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/team";
    Sanctum::actingAs($this->organizer);
});

it('lista a equipe com papel, acesso, último acesso, convites pendentes e totais', function (): void {
    $this->organizer->createToken('painel')->accessToken->forceFill(['last_used_at' => now()->subHour()])->save();
    EditionInvitation::factory()->for($this->edition)->create(['email' => 'thiago@example.com', 'role' => EditionRole::CheckIn]);
    EditionInvitation::factory()->for($this->edition)->expired()->create();

    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.members.0.name', 'Rita Castro')
        ->assertJsonPath('data.members.0.email', 'rita@example.com')
        ->assertJsonPath('data.members.0.role_label', 'Organizador')
        ->assertJsonPath('data.members.0.access', 'Tudo na edição')
        ->assertJsonPath('data.members.0.last_seen_at', fn (?string $date): bool => $date !== null)
        ->assertJsonPath('data.members.1.role_label', 'Revisor do CFP')
        ->assertJsonPath('data.members.1.last_seen_at', null)
        ->assertJsonCount(1, 'data.invitations')
        ->assertJsonPath('data.invitations.0.role_label', 'Credenciamento')
        ->assertJsonPath('data.summary.people', 2)
        ->assertJsonPath('data.summary.pending_invitations', 1)
        ->assertJsonPath('data.summary.by_role.organizador', 1)
        ->assertJsonPath('data.summary.by_role.avaliador', 1)
        ->assertJsonPath('data.summary.by_role.voluntario', 0);
});

it('troca o papel e registra na auditoria', function (): void {
    $this->patchJson("{$this->url}/members/{$this->reviewerMember->id}", ['role' => 'financeiro'])
        ->assertOk()
        ->assertJsonPath('data.role', 'financeiro')
        ->assertJsonPath('data.access', 'Pedidos, pagamentos e finanças');

    $this->assertDatabaseHas('audit_logs', [
        'edition_id' => $this->edition->id,
        'actor_id' => $this->organizer->id,
        'action' => 'team.role_changed',
        'subject_id' => $this->reviewerMember->id,
    ]);
});

it('revoga o acesso e registra na auditoria', function (): void {
    $this->deleteJson("{$this->url}/members/{$this->reviewerMember->id}")->assertNoContent();

    $this->assertDatabaseMissing('edition_members', ['id' => $this->reviewerMember->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'team.removed', 'subject_id' => $this->reviewerMember->id, 'actor_id' => $this->organizer->id]);

    Sanctum::actingAs($this->reviewer);
    $this->getJson("/api/v1/editions/{$this->edition->id}")->assertForbidden();
});

it('bloqueia rebaixar ou remover o último organizador', function (): void {
    $this->patchJson("{$this->url}/members/{$this->organizerMember->id}", ['role' => 'voluntario'])
        ->assertStatus(422)
        ->assertJsonPath('errors.role.0', 'A edição precisa de pelo menos uma pessoa organizadora.');

    $this->deleteJson("{$this->url}/members/{$this->organizerMember->id}")->assertStatus(422);

    $this->assertDatabaseHas('edition_members', ['id' => $this->organizerMember->id, 'role' => 'organizador']);
    $this->assertDatabaseMissing('audit_logs', ['subject_id' => $this->organizerMember->id]);
});

it('permite sair da organização quando há outra pessoa organizadora', function (): void {
    $this->patchJson("{$this->url}/members/{$this->reviewerMember->id}", ['role' => 'organizador'])->assertOk();

    $this->deleteJson("{$this->url}/members/{$this->organizerMember->id}")->assertNoContent();
});

it('não mexe em membro de outra edição', function (): void {
    $foreign = EditionMember::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->patchJson("{$this->url}/members/{$foreign->id}", ['role' => 'voluntario'])->assertNotFound();
    $this->deleteJson("{$this->url}/members/{$foreign->id}")->assertNotFound();
});

it('deixa só quem gerencia a equipe ver e mudar', function (EditionRole $role): void {
    $user = User::factory()->create();
    $member = EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertForbidden();
    $this->patchJson("{$this->url}/members/{$member->id}", ['role' => 'organizador'])->assertForbidden();
    $this->postJson("{$this->url}/invitations", ['email' => 'x@example.com', 'role' => 'organizador'])->assertForbidden();
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);
