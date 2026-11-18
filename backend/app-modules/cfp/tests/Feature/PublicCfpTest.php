<?php

declare(strict_types=1);

use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;

it('mostra a janela e as trilhas do CFP de edição publicada, sem login', function (): void {
    $this->travelTo('2027-03-10 12:00:00');
    $edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    CfpSetting::create(['edition_id' => $edition->id, 'opens_at' => '2027-03-01 00:00:00', 'closes_at' => '2027-04-15 00:00:00']);
    CfpTrack::create(['edition_id' => $edition->id, 'name' => 'Backend', 'slots' => 6, 'position' => 1]);

    $this->getJson("/api/v1/public/editions/{$edition->id}/cfp")
        ->assertOk()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.closes_at', '2027-04-15T00:00:00Z')
        ->assertJsonPath('data.tracks.0.name', 'Backend')
        ->assertJsonPath('data.form.levels.1.label', 'Intermediário')
        ->assertJsonMissingPath('data.tracks.0.slots')
        ->assertJsonMissingPath('data.nordeste_quota_percent');
});

it('não mostra CFP de edição em rascunho ou sem configuração', function (): void {
    $draft = Edition::factory()->create();
    CfpSetting::create(['edition_id' => $draft->id]);

    $this->getJson("/api/v1/public/editions/{$draft->id}/cfp")->assertNotFound();
    $this->getJson('/api/v1/public/editions/'.Edition::factory()->status(EditionStatus::CfpOpen)->create()->id.'/cfp')->assertNotFound();
});
