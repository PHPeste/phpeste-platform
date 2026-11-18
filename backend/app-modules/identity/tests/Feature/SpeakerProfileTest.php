<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Contracts\SpeakerProfiles;
use PHPeste\Identity\Models\User;

$profile = [
    'display_name' => 'Ana Martins',
    'job_title' => 'Engenheira de software',
    'company' => 'PHP-PB',
    'bio' => 'Trabalha com PHP há dez anos e coordena a comunidade local.',
    'city' => 'João Pessoa',
    'state' => 'PB',
    'links' => ['github' => 'https://github.com/anamartins', 'website' => 'https://ana.dev'],
    'accessibility_needs' => 'Rampa de acesso ao palco',
    'dietary_restrictions' => 'Vegetariana',
];

it('a própria pessoa salva e lê o perfil de palestrante, inclusive os campos sensíveis', function () use ($profile): void {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/me/speaker-profile', $profile)
        ->assertOk()
        ->assertJsonPath('data.public.display_name', 'Ana Martins')
        ->assertJsonPath('data.public.state', 'PB')
        ->assertJsonPath('data.public.links', ['website' => 'https://ana.dev', 'github' => 'https://github.com/anamartins', 'linkedin' => null])
        ->assertJsonPath('data.private.dietary_restrictions', 'Vegetariana');

    $this->getJson('/api/v1/me/speaker-profile')->assertOk()->assertJsonPath('data.private.accessibility_needs', 'Rampa de acesso ao palco');
});

it('valida mini bio até 400 caracteres, links https e UF', function () use ($profile): void {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/v1/me/speaker-profile', [
        ...$profile,
        'bio' => str_repeat('a', 401),
        'links' => ['github' => 'javascript:alert(1)', 'twitter' => 'https://x.com/ana'],
        'state' => 'XX',
    ])->assertStatus(422)->assertJsonValidationErrors(['bio', 'links', 'links.github', 'state']);
});

it('guarda os campos sensíveis cifrados no banco', function () use ($profile): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->putJson('/api/v1/me/speaker-profile', $profile)->assertOk();

    $raw = DB::table('speaker_private_details')->where('user_id', $user->id)->first();

    expect($raw->dietary_restrictions)->not->toBe('Vegetariana')->not->toContain('Vegetariana')
        ->and($raw->accessibility_needs)->not->toContain('Rampa');
});

it('o perfil público nunca leva os campos sensíveis', function () use ($profile): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $this->putJson('/api/v1/me/speaker-profile', $profile);

    $public = resolve(SpeakerProfiles::class)->publicProfile($user->id);

    expect($public)->not->toBeNull()
        ->and(json_encode($public))->not->toContain('Vegetariana')->not->toContain('Rampa')->not->toContain($user->email)
        ->and(array_keys((array) $public))->toBe(['user_id', 'display_name', 'job_title', 'company', 'bio', 'city', 'state', 'links', 'photo_url']);
});

it('informa a UF de cada palestrante para a meta do Nordeste', function () use ($profile): void {
    $pb = User::factory()->create();
    Sanctum::actingAs($pb);
    $this->putJson('/api/v1/me/speaker-profile', $profile);
    $sp = User::factory()->create();
    Sanctum::actingAs($sp);
    $this->putJson('/api/v1/me/speaker-profile', [...$profile, 'state' => 'SP']);
    $semPerfil = User::factory()->create();

    expect(resolve(SpeakerProfiles::class)->states([$pb->id, $sp->id, $semPerfil->id]))
        ->toBe([$pb->id => 'PB', $sp->id => 'SP', $semPerfil->id => null]);
});

describe('pela organização da edição', function () use ($profile): void {
    beforeEach(function () use ($profile): void {
        $this->edition = Edition::factory()->create();
        $this->organizer = User::factory()->create();
        EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
        $this->speaker = User::factory()->create();
        EditionMember::factory()->for($this->edition)->create(['user_id' => $this->speaker->id, 'role' => EditionRole::Volunteer]);
        Sanctum::actingAs($this->speaker);
        $this->putJson('/api/v1/me/speaker-profile', $profile);
    });

    it('vê o perfil completo de quem participa da edição', function (): void {
        Sanctum::actingAs($this->organizer);

        $this->getJson("/api/v1/editions/{$this->edition->id}/people/{$this->speaker->id}/speaker-profile")
            ->assertOk()
            ->assertJsonPath('data.public.display_name', 'Ana Martins')
            ->assertJsonPath('data.private.accessibility_needs', 'Rampa de acesso ao palco');
    });

    it('não vê perfil de quem não participa da edição', function (): void {
        $stranger = User::factory()->create();
        Sanctum::actingAs($this->organizer);

        $this->getJson("/api/v1/editions/{$this->edition->id}/people/{$stranger->id}/speaker-profile")->assertNotFound();
    });

    it('pessoa sem permissão de programação não lê o perfil de outra pessoa', function (EditionRole $role): void {
        $user = User::factory()->create();
        EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/editions/{$this->edition->id}/people/{$this->speaker->id}/speaker-profile")->assertForbidden();
    })->with([EditionRole::Reviewer, EditionRole::Finance, EditionRole::CheckIn, EditionRole::Volunteer]);

    it('organização de outra edição não lê', function (): void {
        $other = Edition::factory()->create();
        $foreign = User::factory()->create();
        EditionMember::factory()->for($other)->create(['user_id' => $foreign->id, 'role' => EditionRole::Organizer]);
        Sanctum::actingAs($foreign);

        $this->getJson("/api/v1/editions/{$other->id}/people/{$this->speaker->id}/speaker-profile")->assertNotFound();
        $this->getJson("/api/v1/editions/{$this->edition->id}/people/{$this->speaker->id}/speaker-profile")->assertForbidden();
    });
});
