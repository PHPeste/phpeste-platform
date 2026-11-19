<?php

declare(strict_types=1);

use PHPeste\Community\Enums\BrazilianState;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Enums\EditionRole;

dataset('matriz de permissões', function (): array {
    $allowed = [
        EditionPermission::ManageEdition->value => [EditionRole::Organizer],
        EditionPermission::ManageTeam->value => [EditionRole::Organizer],
        EditionPermission::ReviewProposals->value => [EditionRole::Organizer, EditionRole::Reviewer],
        EditionPermission::ManageProgram->value => [EditionRole::Organizer],
        EditionPermission::ViewSpeakerPrivateData->value => [EditionRole::Organizer],
        EditionPermission::ViewOrders->value => [EditionRole::Organizer, EditionRole::Finance],
        EditionPermission::RefundOrders->value => [EditionRole::Organizer, EditionRole::Finance],
        EditionPermission::ManageGateway->value => [EditionRole::Organizer],
        EditionPermission::CheckIn->value => [EditionRole::Organizer, EditionRole::CheckIn],
        EditionPermission::ManageSponsors->value => [EditionRole::Organizer],
        EditionPermission::ManageSuppliers->value => [EditionRole::Organizer],
        EditionPermission::ManageFinance->value => [EditionRole::Organizer, EditionRole::Finance],
    ];

    $cases = [];

    foreach (EditionRole::cases() as $role) {
        foreach (EditionPermission::cases() as $permission) {
            $cases["{$role->label()} · {$permission->label()}"] = [$role, $permission, in_array($role, $allowed[$permission->value], true)];
        }
    }

    return $cases;
});

it('segue a matriz de permissões do design', function (EditionRole $role, EditionPermission $permission, bool $expected): void {
    expect($role->can($permission))->toBe($expected);
})->with('matriz de permissões');

it('dá nome e resumo de acesso a todo papel', function (EditionRole $role): void {
    expect($role->label())->not->toBe('')->and($role->access())->not->toBe('');
})->with(EditionRole::cases());

it('sabe quais UFs são do Nordeste', function (): void {
    $nordeste = array_values(array_filter(BrazilianState::cases(), fn (BrazilianState $state): bool => $state->isNordeste()));

    expect(array_map(fn (BrazilianState $state): string => $state->value, $nordeste))
        ->toEqualCanonicalizing(['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE']);
});
