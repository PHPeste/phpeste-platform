<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Suppliers\Cloning\SuppliersCloner;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2027-02-10 12:00:00');
    $this->edition = Edition::factory()->create(['name' => 'PHPeste 2027', 'starts_on' => '2027-06-10']);
    $this->organizer = User::factory()->create(['name' => 'Rita Organiza']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->api = "/api/v1/editions/{$this->edition->id}/suppliers";
    Sanctum::actingAs($this->organizer);
});

function supplier(object $test, array $attributes = []): array
{
    return $test->postJson($test->api, [
        'name' => 'Buffet Sertão',
        'service' => 'catering',
        'document' => '12.345.678/0001-90',
        'city' => 'João Pessoa',
        'state' => 'pb',
        'contacts' => [['name' => 'Dona Lúcia', 'role' => 'Comercial', 'email' => 'Lucia@Buffet.com', 'phone' => '83 99999-0000']],
        ...$attributes,
    ])->assertCreated()->json('data');
}

function quote(object $test, string $linkId, int $cents, string $description = 'Coffee break 2 dias'): array
{
    return $test->postJson("{$test->api}/{$linkId}/quotes", ['amount_cents' => $cents, 'description' => $description, 'received_on' => '2027-02-09', 'valid_until' => '2027-03-01'])->assertCreated()->json('data');
}

it('cadastra o fornecedor na comunidade já vinculado à edição, a contatar', function (): void {
    $link = supplier($this);

    expect($link['status'])->toBe('to_contact')
        ->and($link['supplier']['name'])->toBe('Buffet Sertão')
        ->and($link['supplier']['service_label'])->toBe('Alimentação')
        ->and($link['supplier']['document'])->toBe('12345678000190')
        ->and($link['supplier']['state'])->toBe('PB')
        ->and($link['supplier']['contacts_visible'])->toBeTrue()
        ->and($link['supplier']['contacts'][0])->toMatchArray(['name' => 'Dona Lúcia', 'email' => 'lucia@buffet.com'])
        ->and($link['activities'][0]['summary'])->toBe('Fornecedor adicionado à edição.')
        ->and(Supplier::query()->sole()->community_id)->toBe($this->edition->community_id);
});

it('não duplica o fornecedor na mesma comunidade, mas outra comunidade pode ter o seu', function (): void {
    supplier($this);

    $this->postJson($this->api, ['name' => 'buffet  SERTÃO', 'service' => 'catering'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'Buffet Sertão já está no cadastro da comunidade. Use "reaproveitar fornecedor" para trazer para esta edição.');

    $this->postJson($this->api, ['name' => 'Outro Buffet', 'service' => 'catering', 'document' => '12345678000190'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.document.0', 'Este CNPJ ou CPF já é de Buffet Sertão no cadastro da comunidade.');

    $elsewhere = Edition::factory()->create();
    EditionMember::factory()->for($elsewhere)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->postJson("/api/v1/editions/{$elsewhere->id}/suppliers", ['name' => 'Buffet Sertão', 'service' => 'catering'])->assertCreated();

    expect(Supplier::query()->count())->toBe(2);
});

it('reaproveita fornecedor de edição anterior e o contato só aparece para quem é de uma edição em que ele está', function (): void {
    $old = supplier($this);
    $this->postJson("{$this->api}/{$old['id']}/status", ['status' => 'hired', 'contracted_cents' => 800_000])->assertOk();

    $next = Edition::factory()->create(['community_id' => $this->edition->community_id, 'name' => 'PHPeste 2028', 'starts_on' => '2028-06-10']);
    $newcomer = User::factory()->create();
    EditionMember::factory()->for($next)->create(['user_id' => $newcomer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($newcomer);
    $api = "/api/v1/editions/{$next->id}/suppliers";

    $found = $this->getJson("{$api}/directory?q=sertao")->assertOk()->json('data.0');

    expect($found['name'])->toBe('Buffet Sertão')
        ->and($found['contacts_visible'])->toBeFalse()
        ->and($found['contacts'])->toBeNull()
        ->and($found['document'])->toBeNull()
        ->and($found['in_this_edition'])->toBeFalse()
        ->and($found['editions'])->toBe([['id' => $this->edition->id, 'name' => 'PHPeste 2027', 'status' => 'hired', 'status_label' => 'Contratado']]);

    $linked = $this->postJson("{$api}/directory/{$found['id']}")->assertCreated()->json('data');

    expect($linked['status'])->toBe('to_contact')
        ->and($linked['contracted'])->toBeNull()
        ->and($linked['quotes'])->toBe([])
        ->and($linked['supplier']['contacts_visible'])->toBeTrue()
        ->and($linked['supplier']['contacts'][0]['name'])->toBe('Dona Lúcia');

    $this->getJson("{$api}/directory?q=sertao")->assertJsonPath('data.0.in_this_edition', true);
    $this->postJson("{$api}/directory/{$found['id']}")->assertUnprocessable()->assertJsonPath('errors.supplier_id.0', 'Buffet Sertão já está nesta edição.');

    expect(EditionSupplier::query()->forEdition($this->edition->id)->sole()->contracted_cents)->toBe(800_000);
});

it('dados de um fornecedor não vazam para outra comunidade', function (): void {
    $link = supplier($this);
    $supplierId = $link['supplier']['id'];
    $stranger = Edition::factory()->create();
    $intruder = User::factory()->create();
    EditionMember::factory()->for($stranger)->create(['user_id' => $intruder->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($intruder);
    $api = "/api/v1/editions/{$stranger->id}/suppliers";

    $this->getJson("{$api}/directory")->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("{$api}/directory?q=buffet")->assertJsonCount(0, 'data');
    $this->postJson("{$api}/directory/{$supplierId}")->assertNotFound();
    $this->getJson("{$api}/{$link['id']}")->assertNotFound();
    $this->getJson("{$this->api}/{$link['id']}")->assertForbidden();
});

it('orçamentos: o primeiro passa para em orçamento e o orçado é o aceito ou, sem ele, o menor que ainda vale', function (): void {
    $link = supplier($this)['id'];
    $high = quote($this, $link, 900_000);

    $this->getJson("{$this->api}/{$link}")->assertJsonPath('data.status', 'quoting')->assertJsonPath('data.quoted.amount', 900_000);

    $low = quote($this, $link, 700_000, 'Coffee + almoço');
    $this->getJson("{$this->api}/{$link}")->assertJsonPath('data.quoted.amount', 700_000)->assertJsonPath('data.quotes_count', 2);

    $this->postJson("{$this->api}/{$link}/status", ['status' => 'hired', 'quote_id' => $high['id']])->assertOk();
    $this->getJson("{$this->api}/{$link}")->assertJsonPath('data.quoted.amount', 900_000)->assertJsonPath('data.contracted.amount', 900_000);
    $this->getJson($this->api)->assertJsonPath('totals.quoted.amount', 900_000);
    $this->postJson("{$this->api}/{$link}/status", ['status' => 'quoting'])->assertOk();
    $this->getJson("{$this->api}/{$link}")->assertJsonPath('data.quoted.amount', 700_000);

    $this->postJson("{$this->api}/{$link}/quotes/{$low['id']}/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
    $this->getJson("{$this->api}/{$link}")->assertJsonPath('data.quoted.amount', 900_000);

    expect(collect($this->getJson("{$this->api}/{$link}")->json('data.activities'))->pluck('kind')->all())->toContain('quote');
    $this->deleteJson("{$this->api}/{$link}/quotes/{$high['id']}")->assertNoContent();
});

it('contratar usa o orçamento aceito ou um valor; recusar pede o motivo', function (): void {
    $link = supplier($this)['id'];
    $chosen = quote($this, $link, 750_000);

    $this->postJson("{$this->api}/{$link}/status", ['status' => 'hired'])->assertUnprocessable()->assertJsonValidationErrors(['contracted_cents']);

    $hired = $this->postJson("{$this->api}/{$link}/status", ['status' => 'hired', 'quote_id' => $chosen['id']])->assertOk()->json('data');

    expect($hired['status'])->toBe('hired')
        ->and($hired['contracted']['amount'])->toBe(750_000)
        ->and($hired['accepted_quote_id'])->toBe($chosen['id'])
        ->and($hired['quotes'][0]['status'])->toBe('accepted')
        ->and($hired['activities'][0]['summary'])->toBe('Contratado por R$ 7.500,00.')
        ->and(AuditLog::query()->where('action', 'suppliers.status_changed')->where('subject_id', $link)->get()->firstWhere('after.status', 'hired')->after)->toMatchArray(['contracted_cents' => 750_000]);

    $this->deleteJson("{$this->api}/{$link}/quotes/{$chosen['id']}")->assertUnprocessable();
    $this->deleteJson("{$this->api}/{$link}")->assertUnprocessable()->assertJsonValidationErrors(['supplier']);

    $this->postJson("{$this->api}/{$link}/status", ['status' => 'declined'])->assertUnprocessable()->assertJsonValidationErrors(['reason']);
    $declined = $this->postJson("{$this->api}/{$link}/status", ['status' => 'declined', 'reason' => 'Não atende vegano'])->assertOk()->json('data');

    expect($declined['contracted'])->toBeNull()
        ->and($declined['decline_reason'])->toBe('Não atende vegano')
        ->and($declined['quotes'][0]['status'])->toBe('received');
});

it('os totais de orçado e contratado batem com a lista, inclusive filtrada', function (): void {
    $buffet = supplier($this)['id'];
    $grafica = supplier($this, ['name' => 'Gráfica Mandacaru', 'service' => 'printing', 'document' => null, 'contacts' => []])['id'];
    $som = supplier($this, ['name' => 'Som do Agreste', 'service' => 'audio_visual', 'document' => null, 'contacts' => []])['id'];
    quote($this, $buffet, 800_000);
    quote($this, $buffet, 600_000);
    quote($this, $grafica, 150_000);
    quote($this, $som, 400_000);
    $this->postJson("{$this->api}/{$grafica}/status", ['status' => 'hired', 'contracted_cents' => 140_000])->assertOk();
    $this->postJson("{$this->api}/{$som}/status", ['status' => 'declined', 'reason' => 'Caro'])->assertOk();

    $all = $this->getJson($this->api)->assertOk()->json();

    expect(array_column(array_column($all['data'], 'supplier'), 'name'))->toBe(['Buffet Sertão', 'Gráfica Mandacaru', 'Som do Agreste'])
        ->and($all['totals']['quoted']['amount'])->toBe(600_000 + 150_000)
        ->and($all['totals']['contracted']['amount'])->toBe(140_000)
        ->and($all['totals']['by_status'])->toBe(['to_contact' => 0, 'quoting' => 1, 'hired' => 1, 'declined' => 1])
        ->and($all['totals']['quoted']['amount'])->toBe(array_sum(array_map(fn (array $row): int => $row['status'] === 'declined' ? 0 : ($row['quoted']['amount'] ?? 0), $all['data'])))
        ->and($all['totals']['contracted']['amount'])->toBe(array_sum(array_map(fn (array $row): int => $row['contracted']['amount'] ?? 0, $all['data'])));

    $this->getJson("{$this->api}?status=quoting")->assertJsonCount(1, 'data')->assertJsonPath('totals.quoted.amount', 600_000)->assertJsonPath('totals.contracted.amount', 0);
    $this->getJson("{$this->api}?service=printing")->assertJsonCount(1, 'data')->assertJsonPath('totals.contracted.amount', 140_000);
    $this->getJson("{$this->api}?q=agreste")->assertJsonCount(1, 'data');

    $this->getJson("/api/v1/editions/{$this->edition->id}/overview")->assertOk()
        ->assertJsonPath('data.indicators.suppliers.contracted', 140_000)
        ->assertJsonPath('data.indicators.suppliers.quoted', 750_000);
});

it('guarda o histórico com anotações da equipe e registros automáticos', function (): void {
    $link = supplier($this)['id'];

    $detail = $this->postJson("{$this->api}/{$link}/activities", ['channel' => 'visit', 'summary' => 'Visitamos a cozinha', 'happened_at' => '2027-02-09T10:00:00-03:00'])->assertCreated()->json('data');

    expect(array_column($detail['activities'], 'summary'))->toBe(['Fornecedor adicionado à edição.', 'Visitamos a cozinha'])
        ->and($detail['activities'][1]['channel_label'])->toBe('Visita')
        ->and($detail['activities'][1]['author']['name'])->toBe('Rita Organiza');
});

it('editar dados e contatos muda o cadastro da comunidade', function (): void {
    $link = supplier($this);

    $this->patchJson("{$this->api}/{$link['id']}", ['website' => 'https://buffetsertao.com.br', 'owner_id' => $this->organizer->id])
        ->assertOk()
        ->assertJsonPath('data.supplier.website', 'https://buffetsertao.com.br')
        ->assertJsonPath('data.owner.name', 'Rita Organiza');

    $this->patchJson("{$this->api}/{$link['id']}", ['owner_id' => User::factory()->create()->id])
        ->assertUnprocessable()
        ->assertJsonPath('errors.owner_id.0', 'O responsável precisa ser da equipe desta edição.');

    $contact = $this->postJson("{$this->api}/{$link['id']}/contacts", ['name' => 'Seu Zé', 'phone' => '83 98888-0000'])->assertCreated()->json('data');
    $this->patchJson("{$this->api}/{$link['id']}/contacts/{$contact['id']}", ['role' => 'Dono'])->assertOk()->assertJsonPath('data.role', 'Dono');
    $this->deleteJson("{$this->api}/{$link['id']}/contacts/{$link['supplier']['contacts'][0]['id']}")->assertNoContent();

    expect(Supplier::query()->sole()->contacts()->pluck('name')->all())->toBe(['Seu Zé']);
});

it('anexo fica no disco privado e só abre por URL assinada que expira', function (): void {
    $link = supplier($this)['id'];

    $this->post("{$this->api}/{$link}/attachments", ['kind' => 'invoice', 'file' => UploadedFile::fake()->createWithContent('nota.exe', 'MZ')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Para nota fiscal, envie um arquivo pdf, xml, png, jpg, jpeg.');

    $file = $this->post("{$this->api}/{$link}/attachments", ['kind' => 'quote', 'file' => UploadedFile::fake()->createWithContent('orcamento.pdf', '%PDF-1.4 orçamento')], ['Accept' => 'application/json'])
        ->assertCreated()->json('data');

    expect($file)->not->toHaveKey('path')->not->toHaveKey('url');

    $signed = $this->getJson("{$this->api}/attachments/{$file['id']}/url")->assertOk()->json('data');
    $this->app['auth']->forgetGuards();

    $download = $this->get($signed['url'])->assertOk();
    expect($download->headers->get('content-disposition'))->toContain('attachment')
        ->and($download->streamedContent())->toBe('%PDF-1.4 orçamento');

    $this->get(route('suppliers.attachments.download', ['attachment' => $file['id']]))->assertForbidden();
    $this->travel(11)->minutes();
    $this->get($signed['url'])->assertForbidden();
});

it('tirar o fornecedor da edição apaga os anexos daqui e mantém o cadastro', function (): void {
    $link = supplier($this)['id'];
    $this->post("{$this->api}/{$link}/attachments", ['kind' => 'quote', 'file' => UploadedFile::fake()->createWithContent('orcamento.pdf', '%PDF')], ['Accept' => 'application/json'])->assertCreated();

    $this->deleteJson("{$this->api}/{$link}")->assertNoContent();

    expect(EditionSupplier::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and(Supplier::query()->count())->toBe(1)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('a próxima edição recebe os contratados como a contatar', function (): void {
    $hired = supplier($this)['id'];
    supplier($this, ['name' => 'Gráfica Mandacaru', 'service' => 'printing', 'document' => null]);
    $this->postJson("{$this->api}/{$hired}/status", ['status' => 'hired', 'contracted_cents' => 1])->assertOk();
    $next = Edition::factory()->create(['community_id' => $this->edition->community_id]);

    resolve(SuppliersCloner::class)->clone($this->edition->id, $next->id);

    $copied = EditionSupplier::query()->forEdition($next->id)->with('supplier')->sole();

    expect($copied->supplier->name)->toBe('Buffet Sertão')
        ->and($copied->status->value)->toBe('to_contact')
        ->and($copied->contracted_cents)->toBeNull();
});

it('só quem tem a permissão de fornecedores mexe neles', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);

    $this->getJson($this->api)->assertForbidden();
    $this->getJson("{$this->api}/directory")->assertForbidden();
    $this->postJson($this->api, ['name' => 'Gráfica', 'service' => 'other'])->assertForbidden();
});
