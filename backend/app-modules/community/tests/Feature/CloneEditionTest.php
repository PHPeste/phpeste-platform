<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Branding\Models\EditionDomain;
use PHPeste\Branding\Models\EditionSetting;
use PHPeste\Cfp\Models\CfpCriterion;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Cfp\Models\CfpTrack;
use PHPeste\Cfp\Models\Proposal;
use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Models\EditionEmailTemplate;

beforeEach(function (): void {
    Mail::fake();
    Storage::fake('public');
    $this->source = Edition::factory()->status(EditionStatus::Closed)->create(['name' => 'PHPeste 2027', 'slug' => '2027', 'starts_on' => '2027-10-08', 'ends_on' => '2027-10-09', 'city' => 'João Pessoa']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->source)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->reviewer = User::factory()->create();
    EditionMember::factory()->for($this->source)->create(['user_id' => $this->reviewer->id, 'role' => EditionRole::Reviewer]);
    $this->base = "/api/v1/editions/{$this->source->id}";
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->base}/theme/draft/preset", ['preset' => 'mare']);
    $this->postJson("{$this->base}/theme/logos/primary", ['file' => UploadedFile::fake()->image('logo.png', 400, 100)]);
    $this->postJson("{$this->base}/theme/publish")->assertOk();
    $this->postJson("{$this->base}/domains", ['host' => '2027.phpeste.dev']);
    CfpSetting::create(['edition_id' => $this->source->id, 'opens_at' => '2027-03-01', 'closes_at' => '2027-04-15', 'nordeste_quota_percent' => 60, 'proposals_per_person' => 2, 'anonymous_review' => false]);
    CfpTrack::create(['edition_id' => $this->source->id, 'name' => 'Backend', 'slots' => 6, 'position' => 1]);
    CfpCriterion::create(['edition_id' => $this->source->id, 'name' => 'Relevância', 'weight' => 3, 'position' => 1]);
    EditionEmailTemplate::create(['edition_id' => $this->source->id, 'template' => 'order_confirmed', 'subject' => 'Ingresso garantido!', 'body' => 'Oi, {nome}!']);
    Proposal::create(['edition_id' => $this->source->id, 'number' => 1, 'title' => 'Proposta de 2027', 'status' => 'submitted']);
});

function cloneEdition(object $test, array $parts, string $slug = '2028'): TestResponse
{
    return $test->postJson("{$test->base}/clone", ['name' => 'PHPeste 2028', 'slug' => $slug, 'parts' => $parts]);
}

it('lista o que pode ser copiado e o que nunca é', function (): void {
    $this->getJson("{$this->base}/clone-options")
        ->assertOk()
        ->assertJsonPath('data.parts', fn (array $parts): bool => collect($parts)->pluck('id')->sort()->values()->all() === ['cfp', 'emails', 'identity', 'sponsor_tiers', 'suppliers', 'team', 'ticketing'])
        ->assertJsonPath('data.never_copied', fn (array $items): bool => in_array('Propostas', $items, true) && in_array('Participantes', $items, true));
});

it('cria a nova edição em rascunho, sem datas, copiando o que foi escolhido', function (): void {
    $response = cloneEdition($this, ['identity', 'team', 'cfp', 'emails'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'done');

    $target = Edition::query()->findOrFail($response->json('data.target_edition_id'));

    expect($target->status)->toBe(EditionStatus::Draft)
        ->and($target->starts_on)->toBeNull()
        ->and($target->ends_on)->toBeNull()
        ->and($target->community_id)->toBe($this->source->community_id)
        ->and($target->city)->toBe('João Pessoa')
        ->and($target->roleOf($this->organizer->id))->toBe(EditionRole::Organizer)
        ->and($target->roleOf($this->reviewer->id))->toBe(EditionRole::Reviewer);

    $settings = CfpSetting::query()->forEdition($target->id)->firstOrFail();
    expect($settings->opens_at)->toBeNull()
        ->and($settings->nordeste_quota_percent)->toBe(60)
        ->and($settings->anonymous_review)->toBeFalse()
        ->and(CfpTrack::query()->forEdition($target->id)->pluck('name')->all())->toBe(['Backend'])
        ->and(CfpCriterion::query()->forEdition($target->id)->value('weight'))->toBe(3)
        ->and(EditionEmailTemplate::query()->forEdition($target->id)->value('subject'))->toBe('Ingresso garantido!');

    $theme = EditionSetting::query()->forEdition($target->id)->firstOrFail();
    expect($theme->theme_draft['colors']['bg'])->toBe('#0F1B2D')
        ->and($theme->theme_published)->not->toBeNull();
});

it('copia os arquivos de logo em vez de compartilhar a referência', function (): void {
    $targetId = cloneEdition($this, ['identity'])->json('data.target_edition_id');

    $original = EditionSetting::query()->forEdition($this->source->id)->firstOrFail()->theme_published['logos']['primary'] ?? null;
    $copy = EditionSetting::query()->forEdition($targetId)->firstOrFail()->theme_published['logos']['primary'] ?? null;

    expect($copy)->not->toBeNull()->not->toBe($original)->toStartWith("branding/{$targetId}/");
    Storage::disk('public')->assertExists([(string) $original, (string) $copy]);

    Storage::disk('public')->delete((string) $original);
    Storage::disk('public')->assertExists((string) $copy);
});

it('nunca copia dados de pessoas, domínios nem datas', function (): void {
    $targetId = cloneEdition($this, ['identity', 'team', 'cfp', 'emails'])->json('data.target_edition_id');

    expect(Proposal::query()->forEdition($targetId)->count())->toBe(0)
        ->and(EditionDomain::query()->forEdition($targetId)->count())->toBe(0)
        ->and(Proposal::query()->forEdition($this->source->id)->count())->toBe(1);
});

it('copia só o que foi escolhido e sempre coloca quem pediu como organizador', function (): void {
    $targetId = cloneEdition($this, ['cfp'])->json('data.target_edition_id');
    $target = Edition::query()->findOrFail($targetId);

    expect($target->roleOf($this->organizer->id))->toBe(EditionRole::Organizer)
        ->and($target->roleOf($this->reviewer->id))->toBeNull()
        ->and(EditionSetting::query()->forEdition($targetId)->exists())->toBeFalse()
        ->and(EditionEmailTemplate::query()->forEdition($targetId)->exists())->toBeFalse()
        ->and(CfpTrack::query()->forEdition($targetId)->count())->toBe(1);
});

it('é tudo ou nada: se uma parte falha, nenhuma edição nem dado é criado', function (): void {
    app()->tag([FailingCloner::class], EditionCloner::TAG);
    $editions = Edition::query()->count();

    cloneEdition($this, ['cfp', 'team', 'falha'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.error', 'Disco cheio')
        ->assertJsonPath('data.target_edition_id', null);

    expect(Edition::query()->count())->toBe($editions)
        ->and(CfpTrack::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('rejeita slug que já existe na comunidade e parte desconhecida', function (): void {
    cloneEdition($this, ['cfp'], '2027')->assertStatus(422)->assertJsonValidationErrors(['slug']);
    cloneEdition($this, ['pedidos'])->assertStatus(422)->assertJsonValidationErrors(['parts.0']);
});

it('só a organização da edição de origem clona, e só quem pediu acompanha', function (): void {
    $cloneId = cloneEdition($this, ['cfp'])->json('data.id');
    $this->getJson("/api/v1/edition-clones/{$cloneId}")->assertOk()->assertJsonPath('data.status', 'done');

    Sanctum::actingAs($this->reviewer);
    cloneEdition($this, ['cfp'], '2029')->assertForbidden();
    $this->getJson("/api/v1/edition-clones/{$cloneId}")->assertNotFound();
});

class FailingCloner implements EditionCloner
{
    public function key(): string
    {
        return 'falha';
    }

    public function label(): string
    {
        return 'Falha';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        throw new RuntimeException('Disco cheio');
    }
}
