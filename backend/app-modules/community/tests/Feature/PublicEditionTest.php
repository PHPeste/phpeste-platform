<?php

declare(strict_types=1);

use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Community;
use PHPeste\Community\Models\Edition;

it('resolve a edição publicada pelo slug da comunidade e da edição, sem login', function (): void {
    $community = Community::factory()->create(['name' => 'PHP-PB', 'slug' => 'php-pb']);
    $edition = Edition::factory()->for($community)->status(EditionStatus::CfpOpen)->create(['slug' => '2027']);

    $this->getJson('/api/v1/public/communities/php-pb/editions/2027')
        ->assertOk()
        ->assertJsonPath('data.id', $edition->id)
        ->assertJsonPath('data.status', 'cfp_open')
        ->assertJsonPath('data.community.slug', 'php-pb')
        ->assertJsonMissingPath('data.role');
});

it('não expõe edição em rascunho', function (): void {
    $community = Community::factory()->create(['slug' => 'php-pb']);
    Edition::factory()->for($community)->create(['slug' => '2027']);

    $this->getJson('/api/v1/public/communities/php-pb/editions/2027')->assertNotFound();
});

it('não resolve a edição de uma comunidade pelo slug de outra', function (): void {
    Community::factory()->create(['slug' => 'php-pb']);
    $other = Community::factory()->create(['slug' => 'php-pe']);
    Edition::factory()->for($other)->status(EditionStatus::SalesOpen)->create(['slug' => '2027']);

    $this->getJson('/api/v1/public/communities/php-pb/editions/2027')->assertNotFound();
    $this->getJson('/api/v1/public/communities/php-pe/editions/2027')->assertOk();
});

it('responde 404 para comunidade ou edição inexistente', function (): void {
    $this->getJson('/api/v1/public/communities/nada/editions/2027')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');
});
