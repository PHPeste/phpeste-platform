<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Sponsors\Cloning\SponsorTiersCloner;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\SponsorAttachment;
use PHPeste\Sponsors\Models\SponsorDeliverable;
use PHPeste\Sponsors\Models\SponsorSetting;
use PHPeste\Sponsors\Models\SponsorTier;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2027-03-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $this->organizer = User::factory()->create(['name' => 'Rita Organiza']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->api = "/api/v1/editions/{$this->edition->id}/sponsors";
    Sanctum::actingAs($this->organizer);
});

function tier(object $test, array $attributes = []): array
{
    return $test->postJson("{$test->api}/tiers", [
        'name' => 'Ouro',
        'price_cents' => 1_000_000,
        'slots' => 2,
        'benefits' => ['Logo no telão', 'Estande', '2 ingressos'],
        ...$attributes,
    ])->assertCreated()->json('data');
}

function company(object $test, string $name = 'Acme'): array
{
    return $test->postJson("{$test->api}/companies", ['name' => $name, 'contact_name' => 'Joana', 'contact_email' => 'joana@acme.com'])->assertCreated()->json('data');
}

function deal(object $test, string $companyId, array $attributes = []): array
{
    return $test->postJson("{$test->api}/companies/{$companyId}/sponsorships", $attributes)->assertCreated()->json('data');
}

function move(object $test, string $dealId, string $stage, array $extra = []): TestResponse
{
    return $test->postJson("{$test->api}/sponsorships/{$dealId}/move", ['stage' => $stage, ...$extra]);
}

it('cadastra cotas com valor, vagas e contrapartidas', function (): void {
    $gold = tier($this);

    expect($gold['price'])->toBe(['amount' => 1_000_000, 'currency' => 'BRL'])
        ->and($gold['slots'])->toBe(2)
        ->and($gold['slots_left'])->toBe(2)
        ->and($gold['benefits'])->toBe(['Logo no telão', 'Estande', '2 ingressos']);

    $this->postJson("{$this->api}/tiers", ['name' => 'Ouro', 'price_cents' => 1])->assertUnprocessable()->assertJsonPath('errors.name.0', 'Já existe uma cota com esse nome.');
    $this->patchJson("{$this->api}/tiers/{$gold['id']}", ['slots' => null])->assertOk()->assertJsonPath('data.slots_left', null);
});

it('o acordo nasce na prospecção com o preço da cota e aparece no funil', function (): void {
    $gold = tier($this);
    $acme = company($this);
    $created = deal($this, $acme['id'], ['sponsor_tier_id' => $gold['id'], 'owner_id' => $this->organizer->id]);

    expect($created['stage'])->toBe('prospecting')
        ->and($created['value'])->toBe(['amount' => 1_000_000, 'currency' => 'BRL'])
        ->and($created['owner'])->toBe(['id' => $this->organizer->id, 'name' => 'Rita Organiza'])
        ->and($created['tier']['name'])->toBe('Ouro');

    deal($this, company($this, 'Beta')['id'], ['value_cents' => 300_000, 'stage' => 'negotiating']);

    $board = $this->getJson("{$this->api}/board")->assertOk()->json('data');

    expect(array_column($board['columns'], 'stage'))->toBe(['prospecting', 'negotiating', 'closed', 'paid', 'lost'])
        ->and($board['columns'][0]['cards'][0]['sponsor']['name'])->toBe('Acme')
        ->and($board['columns'][1]['value']['amount'])->toBe(300_000)
        ->and($board['indicators']['pipeline']['amount'])->toBe(1_300_000)
        ->and($board['indicators']['committed']['amount'])->toBe(0);

    $this->postJson("{$this->api}/companies/{$acme['id']}/sponsorships", [])->assertUnprocessable()->assertJsonValidationErrors(['value_cents']);
});

it('fechar o acordo cria uma entrega por contrapartida, uma vez só', function (): void {
    $gold = tier($this);
    $id = deal($this, company($this)['id'], ['sponsor_tier_id' => $gold['id'], 'owner_id' => $this->organizer->id])['id'];

    $closed = move($this, $id, 'closed')->assertOk()->json('data');

    expect($closed['stage'])->toBe('closed')
        ->and($closed['closed_at'])->not->toBeNull()
        ->and(array_column($closed['deliverables'], 'title'))->toBe(['Logo no telão', 'Estande', '2 ingressos'])
        ->and($closed['deliverables'][0]['owner']['id'])->toBe($this->organizer->id);

    move($this, $id, 'negotiating')->assertOk();
    move($this, $id, 'closed')->assertOk();

    expect(SponsorDeliverable::query()->forEdition($this->edition->id)->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'sponsors.stage_changed')->count())->toBe(4);
});

it('fechar mais acordos do que vagas na cota exige confirmação', function (): void {
    $gold = tier($this, ['slots' => 1]);
    move($this, deal($this, company($this, 'Acme')['id'], ['sponsor_tier_id' => $gold['id']])['id'], 'closed')->assertOk();
    $second = deal($this, company($this, 'Beta')['id'], ['sponsor_tier_id' => $gold['id']])['id'];

    move($this, $second, 'closed')
        ->assertUnprocessable()
        ->assertJsonPath('errors.confirm.0', 'A cota Ouro tem 1 vaga e 1 já está fechada. Confirme para fechar mais um acordo nela.');

    $this->postJson("{$this->api}/companies/".company($this, 'Gama')['id'].'/sponsorships', ['sponsor_tier_id' => $gold['id'], 'stage' => 'closed'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['confirm']);

    move($this, $second, 'closed', ['confirm' => true])->assertOk()->assertJsonPath('data.stage', 'closed');

    expect(AuditLog::query()->where('action', 'sponsors.stage_changed')->where('subject_id', $second)->get()->firstWhere('before.stage', 'prospecting')->after)->toMatchArray(['stage' => 'closed', 'over_slots' => true]);

    $this->getJson("{$this->api}/tiers")->assertJsonPath('data.0.slots_taken', 2)->assertJsonPath('data.0.slots_left', 0);
    $this->patchJson("{$this->api}/tiers/{$gold['id']}", ['slots' => 1])->assertUnprocessable()->assertJsonValidationErrors(['slots']);
});

it('trocar a cota de um acordo fechado também respeita as vagas', function (): void {
    $gold = tier($this, ['slots' => 1]);
    $silver = tier($this, ['name' => 'Prata', 'price_cents' => 500_000, 'slots' => 5]);
    move($this, deal($this, company($this, 'Acme')['id'], ['sponsor_tier_id' => $gold['id']])['id'], 'closed')->assertOk();
    $beta = deal($this, company($this, 'Beta')['id'], ['sponsor_tier_id' => $silver['id'], 'stage' => 'closed']);

    $this->patchJson("{$this->api}/sponsorships/{$beta['id']}", ['sponsor_tier_id' => $gold['id']])->assertUnprocessable()->assertJsonValidationErrors(['confirm']);
    $this->patchJson("{$this->api}/sponsorships/{$beta['id']}", ['sponsor_tier_id' => $gold['id'], 'confirm' => true])
        ->assertOk()
        ->assertJsonPath('data.tier.name', 'Ouro')
        ->assertJsonPath('data.value.amount', 1_000_000);
});

it('registrar o pagamento inteiro passa o acordo para pago; remover volta para fechado', function (): void {
    $gold = tier($this);
    $id = deal($this, company($this)['id'], ['sponsor_tier_id' => $gold['id']])['id'];
    $pay = fn (int $cents) => $this->postJson("{$this->api}/sponsorships/{$id}/payments", ['amount_cents' => $cents, 'paid_on' => '2027-03-09', 'note' => 'PIX']);

    $pay(400_000)->assertUnprocessable()->assertJsonPath('errors.sponsorship.0', 'Feche o acordo antes de registrar pagamento.');
    move($this, $id, 'closed')->assertOk();
    move($this, $id, 'paid')->assertUnprocessable()->assertJsonPath('errors.stage.0', 'Faltam R$ 10.000,00 para este acordo estar pago. Registre o pagamento primeiro.');

    $pay(400_000)->assertCreated()->assertJsonPath('data.stage', 'closed')->assertJsonPath('data.outstanding.amount', 600_000);
    $pay(700_000)->assertUnprocessable()->assertJsonPath('errors.amount_cents.0', 'Passa do valor do acordo: faltam só R$ 6.000,00.');
    $paid = $pay(600_000)->assertCreated()->json('data');

    expect($paid['stage'])->toBe('paid')
        ->and($paid['paid_at'])->not->toBeNull()
        ->and($paid['received']['amount'])->toBe(1_000_000)
        ->and($paid['payments'][0]['recorded_by']['name'])->toBe('Rita Organiza');

    $this->getJson("{$this->api}/board")->assertJsonPath('data.indicators.received.amount', 1_000_000)->assertJsonPath('data.indicators.committed.amount', 1_000_000);

    $this->deleteJson("{$this->api}/sponsorships/{$id}/payments/{$paid['payments'][1]['id']}")->assertNoContent();
    $this->getJson("{$this->api}/sponsorships/{$id}")->assertJsonPath('data.stage', 'closed')->assertJsonPath('data.paid_at', null);

    expect(AuditLog::query()->where('action', 'sponsors.payment_recorded')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'sponsors.payment_removed')->count())->toBe(1);

    $this->deleteJson("{$this->api}/sponsorships/{$id}")->assertUnprocessable();
});

it('perder o acordo pede o motivo', function (): void {
    $id = deal($this, company($this)['id'], ['value_cents' => 100_000])['id'];

    move($this, $id, 'lost')->assertUnprocessable()->assertJsonValidationErrors(['lost_reason']);
    move($this, $id, 'lost', ['lost_reason' => 'Sem orçamento este ano'])->assertOk()
        ->assertJsonPath('data.stage', 'lost')
        ->assertJsonPath('data.lost_reason', 'Sem orçamento este ano');
    move($this, $id, 'negotiating')->assertOk()->assertJsonPath('data.lost_reason', null)->assertJsonPath('data.lost_at', null);
});

it('entregas têm responsável da equipe, prazo e status, e as atrasadas vão para a visão geral', function (): void {
    $gold = tier($this);
    $id = deal($this, company($this)['id'], ['sponsor_tier_id' => $gold['id']])['id'];
    $first = move($this, $id, 'closed')->json('data.deliverables.0.id');
    $outsider = User::factory()->create();

    $this->patchJson("{$this->api}/deliverables/{$first}", ['owner_id' => $outsider->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.owner_id.0', 'O responsável precisa ser da equipe desta edição.');

    $this->patchJson("{$this->api}/deliverables/{$first}", ['owner_id' => $this->organizer->id, 'due_on' => '2027-03-01'])
        ->assertOk()
        ->assertJsonPath('data.overdue', true);
    $this->postJson("{$this->api}/sponsorships/{$id}/deliverables", ['title' => 'Post nas redes', 'due_on' => '2027-04-01'])->assertCreated();

    $this->getJson("{$this->api}/deliverables?overdue=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Logo no telão')
        ->assertJsonPath('data.0.sponsor.name', 'Acme');

    $overview = $this->getJson("/api/v1/editions/{$this->edition->id}/overview")->assertOk()->json('data');
    $pending = collect($overview['pending'])->firstWhere('key', 'sponsor_deliverables_overdue');

    expect($pending['title'])->toBe('1 entrega de patrocinador atrasada')
        ->and($overview['indicators']['sponsors']['committed'])->toBe(1_000_000)
        ->and($overview['indicators']['sponsors']['stages']['closed'])->toBe(['count' => 1, 'value' => 1_000_000]);

    $this->patchJson("{$this->api}/deliverables/{$first}", ['status' => 'done'])->assertOk()->assertJsonPath('data.overdue', false);
    $this->getJson("{$this->api}/deliverables?overdue=1")->assertJsonCount(0, 'data');
});

it('a meta entra nos indicadores do funil', function (): void {
    $this->patchJson("{$this->api}/settings", ['goal_cents' => 5_000_000])->assertOk()->assertJsonPath('data.goal.amount', 5_000_000);

    $this->getJson("{$this->api}/board")->assertJsonPath('data.indicators.goal.amount', 5_000_000);
});

it('guarda o histórico de contatos com a empresa', function (): void {
    $acme = company($this);
    $contacts = "{$this->api}/companies/{$acme['id']}/contacts";

    $this->postJson($contacts, ['channel' => 'meeting', 'happened_at' => '2027-03-01T15:00:00-03:00', 'summary' => 'Apresentamos as cotas'])->assertCreated();
    $detail = $this->postJson($contacts, ['channel' => 'email', 'summary' => 'Mandei a proposta'])->assertCreated()->json('data');

    expect(array_column($detail['contacts'], 'summary'))->toBe(['Mandei a proposta', 'Apresentamos as cotas'])
        ->and($detail['contacts'][1]['channel_label'])->toBe('Reunião')
        ->and($detail['contacts'][0]['author']['name'])->toBe('Rita Organiza');

    $this->postJson($contacts, ['channel' => 'email', 'happened_at' => '2027-12-01', 'summary' => 'Futuro'])->assertUnprocessable();
});

it('anexo fica no disco privado e só abre por URL assinada que expira', function (): void {
    $acme = company($this);
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>';

    $logo = $this->post("{$this->api}/companies/{$acme['id']}/attachments", [
        'kind' => 'logo',
        'file' => UploadedFile::fake()->createWithContent('acme.svg', $svg),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');

    expect($logo)->not->toHaveKey('path')->not->toHaveKey('url')
        ->and($logo['name'])->toBe('acme.svg');

    $stored = SponsorAttachment::query()->forEdition($this->edition->id)->sole();

    expect(Storage::disk('local')->get($stored->path))->not->toContain('script')->toContain('<rect')
        ->and($stored->path)->toStartWith("sponsors/{$this->edition->id}/");

    $signed = $this->getJson("{$this->api}/attachments/{$logo['id']}/url")->assertOk()->json('data');

    expect($signed['url'])->toContain('signature=')->toContain('expires=')
        ->and($signed['expires_at'])->toBe('2027-03-10T12:10:00Z');

    $this->app['auth']->forgetGuards();
    $download = $this->get($signed['url'])->assertOk();

    expect($download->headers->get('content-disposition'))->toContain('attachment')
        ->and($download->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($download->streamedContent())->toContain('<rect');

    $this->get(str_replace('signature=', 'signature=x', $signed['url']))->assertForbidden();
    $this->get(route('sponsors.attachments.download', ['attachment' => $logo['id']]))->assertForbidden();

    $this->travel(11)->minutes();
    $this->get($signed['url'])->assertForbidden();
});

it('recusa anexo com extensão que não combina com o tipo e apaga o arquivo ao remover', function (): void {
    $acme = company($this);

    $this->post("{$this->api}/companies/{$acme['id']}/attachments", ['kind' => 'contract', 'file' => UploadedFile::fake()->createWithContent('contrato.exe', 'MZ')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Para contrato, envie um arquivo pdf, doc, docx, odt.');

    $contract = $this->post("{$this->api}/companies/{$acme['id']}/attachments", ['kind' => 'contract', 'file' => UploadedFile::fake()->create('contrato.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertCreated()->json('data');

    $this->getJson("{$this->api}/companies/{$acme['id']}")->assertJsonPath('data.attachments.0.kind', 'contract');
    $this->deleteJson("{$this->api}/attachments/{$contract['id']}")->assertNoContent();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('empresa ou cota com dinheiro envolvido não some', function (): void {
    $gold = tier($this);
    $acme = company($this);
    $id = deal($this, $acme['id'], ['sponsor_tier_id' => $gold['id'], 'stage' => 'closed'])['id'];

    $this->deleteJson("{$this->api}/tiers/{$gold['id']}")->assertUnprocessable()->assertJsonValidationErrors(['tier']);

    $this->postJson("{$this->api}/sponsorships/{$id}/payments", ['amount_cents' => 100, 'paid_on' => '2027-03-10'])->assertCreated();
    $this->deleteJson("{$this->api}/companies/{$acme['id']}")->assertUnprocessable()->assertJsonValidationErrors(['sponsor']);

    $beta = company($this, 'Beta');
    deal($this, $beta['id'], ['value_cents' => 10]);
    $this->deleteJson("{$this->api}/companies/{$beta['id']}")->assertNoContent();
});

it('criar a edição a partir da anterior leva as cotas e a meta, não as empresas', function (): void {
    tier($this);
    $this->patchJson("{$this->api}/settings", ['goal_cents' => 5_000_000])->assertOk();
    deal($this, company($this)['id'], ['value_cents' => 10]);
    $next = Edition::factory()->create();

    resolve(SponsorTiersCloner::class)->clone($this->edition->id, $next->id);

    expect(SponsorTier::query()->forEdition($next->id)->sole()->only(['name', 'price_cents', 'slots', 'benefits']))
        ->toBe(['name' => 'Ouro', 'price_cents' => 1_000_000, 'slots' => 2, 'benefits' => ['Logo no telão', 'Estande', '2 ingressos']])
        ->and(Sponsor::query()->forEdition($next->id)->count())->toBe(0)
        ->and(SponsorSetting::query()->forEdition($next->id)->value('goal_cents'))->toBe(5_000_000);
});

it('só quem tem a permissão de patrocinadores mexe no funil', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);

    $this->getJson("{$this->api}/board")->assertForbidden();
    $this->postJson("{$this->api}/companies", ['name' => 'Acme'])->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("{$this->api}/board")->assertForbidden();
});

it('não enxerga dados de outra edição', function (): void {
    $acme = company($this);
    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->getJson("/api/v1/editions/{$other->id}/sponsors/companies/{$acme['id']}")->assertNotFound();
});
