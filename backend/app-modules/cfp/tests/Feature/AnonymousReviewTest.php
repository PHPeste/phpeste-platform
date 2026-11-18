<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\SpeakerProfile;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    Mail::fake();
    $this->travelTo('2027-03-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $this->settings = CfpSetting::create(['edition_id' => $this->edition->id, 'opens_at' => '2027-03-01 00:00:00', 'closes_at' => '2027-04-15 00:00:00']);
    $track = CfpTrack::create(['edition_id' => $this->edition->id, 'name' => 'Backend', 'slots' => 6, 'position' => 1]);

    $this->author = User::factory()->create(['name' => 'Ana Martins Secreta', 'email' => 'ana.secreta@example.com']);
    SpeakerProfile::create(['user_id' => $this->author->id, 'display_name' => 'Ana Martins Secreta', 'state' => 'PB', 'links' => []]);
    Sanctum::actingAs($this->author);
    $base = "/api/v1/speaker/editions/{$this->edition->id}/proposals";
    $this->proposalId = $this->postJson($base, [
        'title' => 'Octane em produção', 'track_id' => $track->id, 'summary' => 'Resumo.', 'audience' => 'Público.', 'format' => 'talk', 'level' => 'advanced',
    ])->json('data.id');
    $this->postJson("{$base}/{$this->proposalId}/submit")->assertOk();
    $this->postJson($base, ['title' => 'Rascunho que ninguém vê']);

    $this->reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->reviewer->id, 'role' => EditionRole::Reviewer]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->admin = "/api/v1/editions/{$this->edition->id}/cfp/proposals";
});

it('revisor nunca recebe nome nem e-mail do autor durante a avaliação anônima', function (): void {
    Sanctum::actingAs($this->reviewer);

    $list = $this->getJson($this->admin)->assertOk()->assertJsonCount(1, 'data');
    $show = $this->getJson("{$this->admin}/{$this->proposalId}")->assertOk();

    foreach ([$list, $show] as $response) {
        expect($response->getContent())
            ->not->toContain('Ana Martins Secreta')
            ->not->toContain('ana.secreta@example.com')
            ->not->toContain($this->author->id);
    }

    $show->assertJsonPath('data.speakers_hidden', true)
        ->assertJsonMissingPath('data.speakers')
        ->assertJsonPath('data.nordeste', true);
});

it('organização vê quem enviou, mesmo com avaliação anônima', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->getJson("{$this->admin}/{$this->proposalId}")
        ->assertOk()
        ->assertJsonPath('data.speakers_hidden', false)
        ->assertJsonPath('data.speakers.0.name', 'Ana Martins Secreta')
        ->assertJsonPath('data.speakers.0.state', 'PB');
});

it('com avaliação aberta, o revisor também vê quem enviou', function (): void {
    $this->settings->update(['anonymous_review' => false]);
    Sanctum::actingAs($this->reviewer);

    $this->getJson("{$this->admin}/{$this->proposalId}")->assertJsonPath('data.speakers.0.name', 'Ana Martins Secreta');
});

it('rascunhos não aparecem para a organização', function (): void {
    Sanctum::actingAs($this->organizer);

    $this->getJson($this->admin)->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Octane em produção');
});

it('quem não avalia nem programa não lista propostas', function (EditionRole $role): void {
    $user = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->admin)->assertForbidden();
})->with([EditionRole::Finance, EditionRole::CheckIn, EditionRole::Volunteer]);
