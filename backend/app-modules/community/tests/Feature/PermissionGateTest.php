<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Identity\Models\User;

it('libera cada permissão só para os papéis da matriz, e só na própria edição', function (EditionRole $role): void {
    $edition = Edition::factory()->create();
    $other = Edition::factory()->create();
    $user = User::factory()->create();
    EditionMember::factory()->for($edition)->create(['user_id' => $user->id, 'role' => $role]);

    foreach (EditionPermission::cases() as $permission) {
        expect(Gate::forUser($user)->allows($permission->value, $edition->id))->toBe($role->can($permission))
            ->and(Gate::forUser($user)->allows($permission->value, $other->id))->toBeFalse()
            ->and(resolve(CurrentEdition::class)->run($edition->id, fn (): bool => Gate::forUser($user)->allows($permission->value)))->toBe($role->can($permission))
            ->and(Gate::forUser($user)->allows($permission->value))->toBeFalse();
    }
})->with(EditionRole::cases());

it('lista papéis e permissões para a tabela do painel', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/roles')
        ->assertOk()
        ->assertJsonPath('data.roles.1.label', 'Financeiro')
        ->assertJsonPath('data.roles.1.permissions', ['orders.view'])
        ->assertJsonPath('data.permissions.0', ['id' => 'edition.manage', 'label' => 'Configurar edição e identidade']);
});
