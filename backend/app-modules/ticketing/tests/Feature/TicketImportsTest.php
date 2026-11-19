<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Actions\ProcessImportChunk;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Imports\ImportPreview;
use PHPeste\Ticketing\Jobs\ProcessTicketImport;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketImport;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

const SYMPLA_CSV = <<<'CSV'
Número do pedido;Código do ingresso;Nome do participante;E-mail do participante;Tipo de ingresso;Nome no crachá;Empresa;E-mail do comprador
P-100;ING-1;Ana Souza;Ana@Exemplo.com;Inteira;Aninha;PHPeste;compras@empresa.com
P-100;ING-2;Bia Lima;bia@exemplo.com;Meia-entrada;;;compras@empresa.com
P-101;ING-3;Caio Rocha;caio@exemplo.com;Inteira;;;
P-102;ING-4;Davi Melo;davi-sem-arroba;Inteira;;;
P-103;ING-5;;eva@exemplo.com;Inteira;;;

P-104;ING-6;Fábio Nunes;fabio@exemplo.com;Caravana;;;
CSV;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->inteira = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Inteira']);
    $this->meia = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Meia-entrada']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->inteira->id, 'name' => '1º lote', 'price_cents' => 0, 'quantity' => 10]);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->imports = "/api/v1/editions/{$this->edition->id}/ticketing/imports";
    Sanctum::actingAs($this->organizer);
});

function uploadCsv(object $test, string $content, string $name = 'sympla.csv'): array
{
    return $test->post($test->imports, [
        'file' => UploadedFile::fake()->createWithContent($name, $content),
        'platform' => 'Sympla',
    ], ['Accept' => 'application/json'])->assertCreated()->json('data');
}

function mapSympla(object $test, string $importId, ?string $caravana = null): array
{
    return $test->putJson("{$test->imports}/{$importId}/mapping", [
        'mapping' => ['order_reference' => 0, 'ticket_code' => 1, 'name' => 2, 'email' => 3, 'ticket_type' => 4, 'badge_name' => 5, 'company' => 6, 'buyer_email' => 7],
        'ticket_type_map' => [
            ['value' => 'Inteira', 'ticket_type_id' => $test->inteira->id],
            ['value' => 'Meia-entrada', 'ticket_type_id' => $test->meia->id],
            ['value' => 'Caravana', 'ticket_type_id' => $caravana],
        ],
    ])->assertOk()->json('data');
}

function importSympla(object $test, string $content = SYMPLA_CSV): string
{
    $import = uploadCsv($test, $content);
    mapSympla($test, $import['id']);
    $test->postJson("{$test->imports}/{$import['id']}/start")->assertStatus(202);

    return $import['id'];
}

it('lê o CSV, descobre separador e cabeçalho e sugere o mapeamento das colunas', function (): void {
    $import = uploadCsv($this, "\xEF\xBB\xBF".SYMPLA_CSV);

    expect($import['status'])->toBe('uploaded')
        ->and($import['rows']['total'])->toBe(6)
        ->and($import['preview']['headers'][2])->toBe('Nome do participante')
        ->and((array) $import['preview']['mapping'])->toBe([
            'buyer_email' => 7,
            'order_reference' => 0,
            'ticket_code' => 1,
            'badge_name' => 5,
            'ticket_type' => 4,
            'email' => 3,
            'name' => 2,
            'company' => 6,
        ])
        ->and(array_column($import['preview']['ticket_type_values'], 'value'))->toBe(['Caravana', 'Inteira', 'Meia-entrada'])
        ->and($import['preview']['ticket_type_values'][1]['ticket_type_id'])->toBe($this->inteira->id)
        ->and($import['preview']['ticket_type_values'][0]['ticket_type_id'])->toBeNull();
});

it('aceita o CSV do Excel em Windows-1252 separado por vírgula', function (): void {
    $content = mb_convert_encoding("Nome,E-mail,Ingresso\nJoão Araújo,joao@exemplo.com,Inteira\n", 'Windows-1252', 'UTF-8');
    $import = uploadCsv($this, $content, 'planilha.csv');

    expect($import['preview']['sample'][0]['values'][0])->toBe('João Araújo')
        ->and($import['preview']['sample'][0]['outcome'])->toBe('import')
        ->and(TicketImport::query()->forEdition($this->edition->id)->sole()->delimiter)->toBe(',');
});

it('a prévia mostra o que vai acontecer com cada linha sem gravar nada', function (): void {
    $import = uploadCsv($this, SYMPLA_CSV);
    $mapped = mapSympla($this, $import['id']);

    expect($mapped['status'])->toBe('ready')
        ->and(array_column($mapped['preview']['sample'], 'outcome'))->toBe(['import', 'import', 'import', 'rejected', 'rejected', 'rejected'])
        ->and($mapped['preview']['sample'][3]['reason'])->toBe('E-mail inválido: "davi-sem-arroba".')
        ->and($mapped['preview']['sample'][4]['reason'])->toBe('Sem nome do participante.')
        ->and($mapped['preview']['sample'][5]['reason'])->toBe('Linhas do tipo "Caravana" ficaram de fora da importação.')
        ->and(Order::query()->forEdition($this->edition->id)->count())->toBe(0);
});

it('não deixa importar sem nome, e-mail e um tipo para cada valor da coluna de tipo', function (): void {
    $import = uploadCsv($this, SYMPLA_CSV);

    $this->putJson("{$this->imports}/{$import['id']}/mapping", ['mapping' => ['name' => 2]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mapping.email']);

    $this->putJson("{$this->imports}/{$import['id']}/mapping", [
        'mapping' => ['name' => 2, 'email' => 3, 'ticket_type' => 4],
        'ticket_type_map' => [['value' => 'Inteira', 'ticket_type_id' => $this->inteira->id]],
    ])->assertUnprocessable()->assertJsonPath('errors.ticket_type_map.0', 'Escolha o tipo de ingresso para: "Caravana", "Meia-entrada". Ou defina um tipo padrão.');

    $this->putJson("{$this->imports}/{$import['id']}/mapping", ['mapping' => ['name' => 2, 'email' => 2]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mapping.email']);

    $this->postJson("{$this->imports}/{$import['id']}/start")
        ->assertUnprocessable()
        ->assertJsonPath('errors.import.0', 'Confirme o mapeamento das colunas antes de importar.');
});

it('cria pedidos externos pagos, sem cobrança e sem taxa, e emite os ingressos sem mandar e-mail', function (): void {
    $id = importSympla($this);

    $orders = Order::query()->forEdition($this->edition->id)->orderBy('number')->get();

    expect($orders)->toHaveCount(2)
        ->and($orders->pluck('external_reference')->all())->toBe(['P-100', 'P-101'])
        ->and(OrderItem::query()->forEdition($this->edition->id)->where('order_id', $orders->first()->id)->pluck('attendee_email')->sort()->values()->all())->toBe(['ana@exemplo.com', 'bia@exemplo.com'])
        ->and($orders->first()->buyer_email)->toBe('compras@empresa.com')
        ->and($orders->last()->buyer_email)->toBe('caio@exemplo.com')
        ->and($orders->every(fn (Order $order): bool => $order->status->value === 'paid' && $order->total_cents === 0 && $order->fee_cents === 0 && $order->source->value === 'import' && $order->external_platform === 'Sympla' && $order->payment_account_id === null))->toBeTrue()
        ->and(PaymentCharge::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and(EmailDelivery::query()->forEdition($this->edition->id)->count())->toBe(0);

    $ana = Ticket::query()->forEdition($this->edition->id)->where('holder_email', 'ana@exemplo.com')->sole();

    expect($ana->status->value)->toBe('active')
        ->and($ana->ticket_type_id)->toBe($this->inteira->id)
        ->and($ana->badge_name)->toBe('Aninha')
        ->and($ana->company)->toBe('PHPeste')
        ->and($ana->last_sent_at)->toBeNull()
        ->and(OrderItem::query()->forEdition($this->edition->id)->where('external_code', 'ING-2')->sole()->ticket_type_id)->toBe($this->meia->id)
        ->and(TicketBatch::query()->forEdition($this->edition->id)->sole()->allocated)->toBe(0)
        ->and(TicketingSetting::query()->forEdition($this->edition->id)->sole()->allocated)->toBe(3);

    $this->getJson("{$this->imports}/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.rows', ['total' => 6, 'processed' => 6, 'imported' => 3, 'duplicates' => 0, 'rejected' => 3])
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.emails.pending', 3)
        ->assertJsonMissingPath('data.preview');

    expect(TicketImport::query()->forEdition($this->edition->id)->sole()->file_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(AuditLog::query()->where('action', 'ticketing.import_started')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'ticketing.import_finished')->value('after'))->toMatchArray(['imported' => 3, 'duplicates' => 0, 'rejected' => 3]);
});

it('reimportar o mesmo arquivo não cria ninguém duas vezes', function (): void {
    importSympla($this);
    $second = importSympla($this);

    expect(Ticket::query()->forEdition($this->edition->id)->count())->toBe(3)
        ->and(Order::query()->forEdition($this->edition->id)->count())->toBe(2);

    $this->getJson("{$this->imports}/{$second}/rejections")
        ->assertOk()
        ->assertJsonPath('data.0', ['line' => 1, 'kind' => 'duplicate', 'reason' => 'O ingresso de código "ING-1" já foi importado.', 'values' => ['P-100', 'ING-1', 'Ana Souza', 'Ana@Exemplo.com', 'Inteira', 'Aninha', 'PHPeste', 'compras@empresa.com']])
        ->assertJsonPath('meta.total', 6);
});

it('sem código do ingresso, quem já tem ingresso do mesmo tipo é repetido', function (): void {
    importSympla($this);
    $import = uploadCsv($this, "Nome;E-mail;Tipo\nAna Souza;ana@exemplo.com;Inteira\nAna Souza;ana@exemplo.com;Meia-entrada\nGabi;gabi@exemplo.com;Inteira\n");
    $this->putJson("{$this->imports}/{$import['id']}/mapping", [
        'mapping' => ['name' => 0, 'email' => 1, 'ticket_type' => 2],
        'ticket_type_map' => [['value' => 'Inteira', 'ticket_type_id' => $this->inteira->id], ['value' => 'Meia-entrada', 'ticket_type_id' => $this->meia->id]],
    ])->assertOk();
    $this->postJson("{$this->imports}/{$import['id']}/start")->assertStatus(202);

    $this->getJson("{$this->imports}/{$import['id']}")
        ->assertJsonPath('data.rows.imported', 2)
        ->assertJsonPath('data.rows.duplicates', 1);

    expect(Ticket::query()->forEdition($this->edition->id)->where('holder_email', 'ana@exemplo.com')->count())->toBe(2);
});

it('sem coluna de tipo, todos recebem o tipo padrão', function (): void {
    $import = uploadCsv($this, "Nome;E-mail\nHugo;hugo@exemplo.com\nIris;iris@exemplo.com\n");

    $this->putJson("{$this->imports}/{$import['id']}/mapping", ['mapping' => ['name' => 0, 'email' => 1]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['default_ticket_type_id']);

    $this->putJson("{$this->imports}/{$import['id']}/mapping", ['mapping' => ['name' => 0, 'email' => 1], 'default_ticket_type_id' => $this->meia->id])->assertOk();
    $this->postJson("{$this->imports}/{$import['id']}/start")->assertStatus(202);

    expect(Ticket::query()->forEdition($this->edition->id)->pluck('ticket_type_id')->unique()->all())->toBe([$this->meia->id])
        ->and(Order::query()->forEdition($this->edition->id)->count())->toBe(2);
});

it('o relatório das linhas de fora sai em CSV com o motivo e os valores originais', function (): void {
    $id = importSympla($this);

    $csv = $this->get("{$this->imports}/{$id}/rejections.csv")->assertOk()->streamedContent();
    $lines = array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), array_values(array_filter(explode("\n", mb_ltrim($csv, "\u{FEFF}")))));

    expect($lines[0])->toBe(['Linha', 'Situação', 'Motivo', 'Número do pedido', 'Código do ingresso', 'Nome do participante', 'E-mail do participante', 'Tipo de ingresso', 'Nome no crachá', 'Empresa', 'E-mail do comprador'])
        ->and(array_column(array_slice($lines, 1), 0))->toBe(['4', '5', '6'])
        ->and($lines[1][2])->toBe('E-mail inválido: "davi-sem-arroba".')
        ->and($lines[3][7])->toBe('Caravana');
});

it('a importação grande vai em lotes na fila e retoma do lote onde parou, sem duplicar', function (): void {
    config()->set('ticketing.imports.chunk_size', 2);
    $explode = true;
    Ticket::creating(function (Ticket $ticket) use (&$explode): void {
        throw_if($explode && $ticket->holder_email === 'caio@exemplo.com', RuntimeException::class, 'worker caiu');
    });

    $import = uploadCsv($this, SYMPLA_CSV);
    mapSympla($this, $import['id']);
    $this->postJson("{$this->imports}/{$import['id']}/start")->assertServerError();

    $stopped = TicketImport::query()->forEdition($this->edition->id)->sole();

    expect($stopped->status)->toBe(ImportStatus::Failed)
        ->and($stopped->processed_rows)->toBe(2)
        ->and($stopped->imported_rows)->toBe(2)
        ->and($stopped->file_path)->not->toBeNull()
        ->and(OrderItem::query()->forEdition($this->edition->id)->where('attendee_email', 'caio@exemplo.com')->exists())->toBeFalse()
        ->and(Ticket::query()->forEdition($this->edition->id)->count())->toBe(2);

    $this->getJson("{$this->imports}/{$import['id']}")->assertJsonPath('data.can_resume', true)->assertJsonPath('data.progress', 33);

    $explode = false;
    $this->postJson("{$this->imports}/{$import['id']}/resume")->assertStatus(202);

    $this->getJson("{$this->imports}/{$import['id']}")
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.rows', ['total' => 6, 'processed' => 6, 'imported' => 3, 'duplicates' => 0, 'rejected' => 3]);

    expect(Ticket::query()->forEdition($this->edition->id)->count())->toBe(3)
        ->and(TicketingSetting::query()->forEdition($this->edition->id)->sole()->allocated)->toBe(3);
});

it('importação parada sem avançar volta para a fila sozinha', function (): void {
    $import = uploadCsv($this, SYMPLA_CSV);
    mapSympla($this, $import['id']);
    $offset = ImportPreview::file(TicketImport::query()->forEdition($this->edition->id)->sole())->dataOffset();
    TicketImport::query()->forEdition($this->edition->id)->update(['status' => 'processing', 'byte_offset' => $offset, 'started_at' => now()]);

    $this->artisan('ticketing:resume-imports')->assertSuccessful();
    expect(TicketImport::query()->forEdition($this->edition->id)->sole()->status)->toBe(ImportStatus::Processing);

    $this->travel(11)->minutes();
    $this->artisan('ticketing:resume-imports')->assertSuccessful();

    expect(TicketImport::query()->forEdition($this->edition->id)->sole()->status)->toBe(ImportStatus::Completed)
        ->and(Ticket::query()->forEdition($this->edition->id)->count())->toBe(3);
});

it('o job que roda duas vezes o mesmo lote não importa de novo', function (): void {
    $import = uploadCsv($this, SYMPLA_CSV);
    mapSympla($this, $import['id']);
    $this->postJson("{$this->imports}/{$import['id']}/start")->assertStatus(202);

    resolve(ProcessTicketImport::class, ['editionId' => $this->edition->id, 'importId' => $import['id']])->handle(resolve(ProcessImportChunk::class));

    expect(Ticket::query()->forEdition($this->edition->id)->count())->toBe(3);
});

it('ingresso importado conta na capacidade da edição', function (): void {
    TicketingSetting::create(['edition_id' => $this->edition->id, 'capacity' => 2]);
    $id = importSympla($this);

    $this->getJson("{$this->imports}/{$id}/rejections")->assertJsonPath('data.0.reason', 'A capacidade da edição esgotou.');

    expect(Ticket::query()->forEdition($this->edition->id)->count())->toBe(2);
});

it('só manda os ingressos por e-mail com confirmação explícita do número de pessoas', function (): void {
    $id = importSympla($this);
    $email = "{$this->imports}/{$id}/email";

    $this->postJson($email, ['confirm' => false, 'recipients' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('errors.confirm.0', 'Confirme o envio do ingresso por e-mail para 3 pessoas.');

    $this->postJson($email, ['confirm' => true, 'recipients' => 300])->assertUnprocessable();

    expect(EmailDelivery::query()->forEdition($this->edition->id)->count())->toBe(0);

    $this->postJson($email, ['confirm' => true, 'recipients' => 3])->assertStatus(202)->assertJsonPath('data.queued', 3);

    expect(EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::OrderConfirmed->value)->pluck('to_email')->sort()->values()->all())
        ->toBe(['ana@exemplo.com', 'bia@exemplo.com', 'caio@exemplo.com'])
        ->and(Ticket::query()->forEdition($this->edition->id)->whereNull('last_sent_at')->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'ticketing.import_emails_sent')->value('after'))->toMatchArray(['recipients' => 3]);

    $this->postJson($email, ['confirm' => true, 'recipients' => 3])
        ->assertUnprocessable()
        ->assertJsonPath('errors.import.0', 'Todos os ingressos desta importação já foram enviados.');
});

it('pedido importado não passa pelo reembolso nem pela conciliação', function (): void {
    importSympla($this);
    $order = Order::query()->forEdition($this->edition->id)->firstOrFail();

    $this->postJson("/api/v1/editions/{$this->edition->id}/ticketing/orders/{$order->id}/refunds", ['reason' => 'requested_by_buyer'])
        ->assertUnprocessable();

    $this->getJson("/api/v1/editions/{$this->edition->id}/ticketing/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.source', 'import')
        ->assertJsonPath('data.external', ['platform' => 'Sympla', 'reference' => 'P-100'])
        ->assertJsonPath('data.items.0.external_code', 'ING-1');
});

it('descarta uma importação que ainda não começou e apaga o arquivo', function (): void {
    $import = uploadCsv($this, SYMPLA_CSV);

    $this->deleteJson("{$this->imports}/{$import['id']}")->assertNoContent();

    expect(TicketImport::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('recusa arquivo vazio, sem cabeçalho ou que não é CSV', function (): void {
    $send = fn (string $name, string $content) => $this->post($this->imports, ['file' => UploadedFile::fake()->createWithContent($name, $content), 'platform' => 'Sympla'], ['Accept' => 'application/json']);

    $send('vazio.csv', "Nome;E-mail\n")->assertUnprocessable()->assertJsonPath('errors.file.0', 'O arquivo só tem o cabeçalho, sem nenhum participante.');
    $send('uma-coluna.csv', "ana@exemplo.com\n")->assertUnprocessable()->assertJsonPath('errors.file.0', 'Não encontrei o cabeçalho. A primeira linha do arquivo precisa ter o nome de cada coluna.');
    $send('foto.png', "\x89PNG\r\n\x1a\n")->assertUnprocessable()->assertJsonValidationErrors(['file']);

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('só quem organiza a edição importa compradores', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);

    $this->getJson($this->imports)->assertForbidden();
    $this->post($this->imports, ['file' => UploadedFile::fake()->createWithContent('a.csv', SYMPLA_CSV), 'platform' => 'Sympla'], ['Accept' => 'application/json'])->assertForbidden();
});
