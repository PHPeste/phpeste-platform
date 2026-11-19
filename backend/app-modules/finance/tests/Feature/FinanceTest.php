<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Finance\Cloning\FinanceCloner;
use PHPeste\Finance\Models\Expense;
use PHPeste\Finance\Models\FinanceCategory;
use PHPeste\Finance\Reports\FinanceReport;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Sponsors\Models\Sponsor;
use PHPeste\Sponsors\Models\Sponsorship;
use PHPeste\Sponsors\Models\SponsorshipPayment;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\Supplier;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketingSetting;

beforeEach(function (): void {
    Storage::fake('local');
    $this->travelTo('2027-05-10 12:00:00');
    $this->edition = Edition::factory()->create();
    $this->finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->finance->id, 'role' => EditionRole::Finance]);
    $this->api = "/api/v1/editions/{$this->edition->id}/finance";
    Sanctum::actingAs($this->finance);
});

function account(string $editionId, string $holder, string $type = 'individual', bool $retired = false): PaymentAccount
{
    return PaymentAccount::create(['edition_id' => $editionId, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'k'], 'credentials_hint' => 'xxxx', 'holder_type' => $type, 'holder_name' => $holder, 'holder_document_masked' => $type === 'individual' ? '***.***.247-**' : '**.***.***/0001-**', 'methods' => ['pix'], 'max_installments' => 1, 'webhook_token' => Str::random(48), 'retired_at' => $retired ? now() : null]);
}

function order(string $editionId, int $number, array $attributes): Order
{
    return Order::create([
        'edition_id' => $editionId,
        'number' => $number,
        'buyer_name' => 'Comprador',
        'buyer_email' => "c{$number}@exemplo.com",
        'subtotal_cents' => $attributes['total_cents'] ?? 0,
        'access_token' => Str::random(48),
        'idempotency_key' => 'k'.$number,
        'request_hash' => hash('sha256', (string) $number),
        ...$attributes,
    ]);
}

function category(object $test, string $name): array
{
    return collect($test->getJson("{$test->api}/categories")->json('data'))->firstWhere('name', $name);
}

it('a receita de ingressos bate com os pedidos pagos menos reembolsos, por conta (propriedade)', function (): void {
    $statuses = ['paid', 'paid', 'paid', 'refunded', 'expired', 'cancelled', 'created', 'awaiting_payment'];

    for ($round = 1; $round <= 25; $round++) {
        mt_srand($round);
        $edition = Edition::factory()->create();
        $basisPoints = mt_rand(0, 600);
        TicketingSetting::create(['edition_id' => $edition->id, 'gateway_fee_basis_points' => $basisPoints]);
        $accounts = [account($edition->id, 'Fulana', 'individual', true)->id, account($edition->id, 'Associação PHPeste', 'company')->id, null];
        $expected = [];

        for ($n = 1; $n <= mt_rand(0, 30); $n++) {
            $status = $statuses[mt_rand(0, count($statuses) - 1)];
            $total = mt_rand(0, 4) === 0 ? 0 : mt_rand(1, 50_000);
            $refunded = in_array($status, ['paid', 'refunded'], true) && $total > 0 ? ($status === 'refunded' ? $total : mt_rand(0, 2) * intdiv($total, 3)) : 0;
            $fee = mt_rand(0, 1) === 0 ? null : mt_rand(0, intdiv($total, 10));
            $accountId = $accounts[mt_rand(0, 2)];

            order($edition->id, $n, ['status' => $status, 'total_cents' => $total, 'refunded_cents' => $refunded, 'gateway_fee_cents' => $fee, 'payment_account_id' => $accountId]);

            if (in_array($status, ['paid', 'refunded'], true) && $total > 0) {
                $key = $accountId ?? 'none';
                $expected[$key] ??= ['gross' => 0, 'refunded' => 0, 'fees' => 0];
                $expected[$key]['gross'] += $total;
                $expected[$key]['refunded'] += $refunded;
                $expected[$key]['fees'] += $fee ?? ($basisPoints === 0 ? 0 : intdiv($total * $basisPoints + 9_999, 10_000));
            }
        }

        $tickets = resolve(FinanceReport::class)->summary($edition->id)['revenue']['tickets'];
        $paidMinusRefunds = array_sum(array_map(fn (array $line): int => $line['gross'] - $line['refunded'], $expected));

        expect($tickets['gross'] - $tickets['refunded'])->toBe($paidMinusRefunds, "rodada {$round}")
            ->and($tickets['net'])->toBe($paidMinusRefunds - array_sum(array_column($expected, 'fees')), "rodada {$round}");

        foreach ($tickets['by_account'] as $line) {
            $key = $line['account']['id'] ?? 'none';

            expect([$line['gross'], $line['refunded'], $line['fees']])->toBe([$expected[$key]['gross'], $expected[$key]['refunded'], $expected[$key]['fees']], "rodada {$round}, conta {$key}")
                ->and($line['net'])->toBe($line['gross'] - $line['refunded'] - $line['fees']);
        }

        expect(count($tickets['by_account']))->toBe(count($expected), "rodada {$round}");
    }
});

it('separa a receita por conta de recebimento, com titular CPF ou CNPJ, para declarar', function (): void {
    TicketingSetting::create(['edition_id' => $this->edition->id, 'gateway_fee_basis_points' => 500]);
    $cpf = account($this->edition->id, 'Fulana de Tal', 'individual', retired: true);
    $cnpj = account($this->edition->id, 'Associação PHPeste', 'company');
    order($this->edition->id, 1, ['status' => 'paid', 'total_cents' => 10_000, 'gateway_fee_cents' => 199, 'payment_account_id' => $cpf->id]);
    order($this->edition->id, 2, ['status' => 'paid', 'total_cents' => 20_000, 'refunded_cents' => 5_000, 'payment_account_id' => $cnpj->id]);
    order($this->edition->id, 3, ['status' => 'paid', 'total_cents' => 0, 'source' => 'import']);

    $lines = collect($this->getJson("{$this->api}/summary")->assertOk()->json('data.revenue.tickets.by_account'))->keyBy('account.holder_name');

    expect($lines['Fulana de Tal'])->toMatchArray(['orders' => 1, 'gross' => 10_000, 'refunded' => 0, 'fees' => 199, 'net' => 9_801, 'estimated_fees' => 0])
        ->and($lines['Fulana de Tal']['account'])->toMatchArray(['holder_type' => 'individual', 'holder_document' => '***.***.247-**', 'retired' => true])
        ->and($lines['Associação PHPeste'])->toMatchArray(['gross' => 20_000, 'refunded' => 5_000, 'fees' => 1_000, 'net' => 14_000, 'estimated_fees' => 1]);
});

it('saldo e previsão somam ingressos, patrocínio e despesas', function (): void {
    order($this->edition->id, 1, ['status' => 'paid', 'total_cents' => 100_000, 'gateway_fee_cents' => 4_000]);
    $sponsor = Sponsor::create(['edition_id' => $this->edition->id, 'name' => 'Acme']);
    $deal = Sponsorship::create(['edition_id' => $this->edition->id, 'sponsor_id' => $sponsor->id, 'stage' => 'closed', 'value_cents' => 500_000]);
    SponsorshipPayment::create(['edition_id' => $this->edition->id, 'sponsorship_id' => $deal->id, 'amount_cents' => 200_000, 'paid_on' => '2027-05-01']);
    $local = category($this, 'Local');

    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'description' => 'Sinal do auditório', 'amount_cents' => 150_000, 'status' => 'paid', 'paid_on' => '2027-05-02'])->assertCreated();
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'description' => 'Restante do auditório', 'amount_cents' => 250_000, 'due_on' => '2027-06-01'])->assertCreated();
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'description' => 'Cancelada', 'amount_cents' => 999_999, 'status' => 'cancelled'])->assertCreated();

    $summary = $this->getJson("{$this->api}/summary")->json('data');

    expect($summary['revenue']['tickets']['net'])->toBe(96_000)
        ->and($summary['revenue']['sponsors'])->toBe(['received' => 200_000, 'outstanding' => 300_000])
        ->and($summary['revenue']['total'])->toBe(296_000)
        ->and($summary['expenses'])->toBe(['paid' => 150_000, 'pending' => 250_000, 'overdue' => 0])
        ->and($summary['balance'])->toBe(146_000)
        ->and($summary['forecast'])->toBe(146_000 + 300_000 - 250_000);
});

it('compara o orçamento de cada categoria com o realizado', function (): void {
    $categories = $this->getJson("{$this->api}/categories")->assertOk()->json('data');

    expect(array_column($categories, 'name'))->toBe(FinanceCategory::DEFAULTS);

    $food = category($this, 'Alimentação');
    $this->patchJson("{$this->api}/categories/{$food['id']}", ['budget_cents' => 100_000])->assertOk()->assertJsonPath('data.budget', 100_000);
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $food['id'], 'description' => 'Coffee', 'amount_cents' => 80_000, 'status' => 'paid', 'paid_on' => '2027-05-09'])->assertCreated();
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $food['id'], 'description' => 'Almoço', 'amount_cents' => 40_000])->assertCreated();

    $budget = $this->getJson("{$this->api}/summary")->json('data.budget');
    $row = collect($budget['categories'])->firstWhere('name', 'Alimentação');

    expect($row)->toMatchArray(['budget' => 100_000, 'committed' => 120_000, 'paid' => 80_000, 'remaining' => -20_000, 'percent_used' => 120, 'over_budget' => true])
        ->and(collect($budget['categories'])->firstWhere('name', 'Local'))->toMatchArray(['budget' => null, 'remaining' => null, 'percent_used' => null])
        ->and($budget['budgeted'])->toBe(100_000);

    $this->deleteJson("{$this->api}/categories/{$food['id']}")->assertUnprocessable()->assertJsonValidationErrors(['category']);
    $this->postJson("{$this->api}/categories", ['name' => 'Local'])->assertUnprocessable();
    $this->postJson("{$this->api}/categories", ['name' => 'Seguro', 'budget_cents' => 30_000])->assertCreated();
});

it('despesa: paga exige data, categoria e fornecedor têm que ser da edição', function (): void {
    $local = category($this, 'Local');
    $created = $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'description' => 'Auditório', 'amount_cents' => 10_000, 'due_on' => '2027-05-01'])
        ->assertCreated()->json('data');

    expect($created)->toMatchArray(['status' => 'pending', 'status_label' => 'Atrasada', 'overdue' => true]);

    $this->patchJson("{$this->api}/expenses/{$created['id']}", ['status' => 'paid'])->assertOk()->assertJsonPath('data.paid_on', '2027-05-10');
    $this->patchJson("{$this->api}/expenses/{$created['id']}", ['paid_on' => '2027-05-12'])->assertUnprocessable();

    $other = Edition::factory()->create();
    $foreignCategory = FinanceCategory::create(['edition_id' => $other->id, 'name' => 'Outra']);
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $foreignCategory->id, 'description' => 'Teste', 'amount_cents' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['finance_category_id']);

    $supplier = Supplier::create(['community_id' => $this->edition->community_id, 'name' => 'Buffet', 'name_key' => 'buffet', 'service' => 'catering']);
    $foreignLink = EditionSupplier::create(['edition_id' => $other->id, 'supplier_id' => $supplier->id, 'status' => 'hired']);
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'edition_supplier_id' => $foreignLink->id, 'description' => 'Teste', 'amount_cents' => 1])
        ->assertUnprocessable()->assertJsonValidationErrors(['edition_supplier_id']);

    $link = EditionSupplier::create(['edition_id' => $this->edition->id, 'supplier_id' => $supplier->id, 'status' => 'hired', 'contracted_cents' => 50_000]);
    $this->getJson("{$this->api}/suppliers")->assertJsonPath('data.0.name', 'Buffet');
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'edition_supplier_id' => $link->id, 'description' => 'Buffet', 'amount_cents' => 50_000])
        ->assertCreated()->assertJsonPath('data.supplier.name', 'Buffet');

    $this->getJson("{$this->api}/expenses?edition_supplier_id={$link->id}")->assertJsonCount(1, 'data')->assertJsonPath('totals.pending', 50_000);

    expect(AuditLog::query()->where('action', 'finance.expense_updated')->where('subject_id', $created['id'])->get()->firstWhere('after.status', 'paid'))->not->toBeNull();

    $this->deleteJson("{$this->api}/expenses/{$created['id']}")->assertNoContent();
    expect(AuditLog::query()->where('action', 'finance.expense_deleted')->exists())->toBeTrue();
});

it('comprovante fica no disco privado e só abre por URL assinada', function (): void {
    $expense = $this->postJson("{$this->api}/expenses", ['finance_category_id' => category($this, 'Local')['id'], 'description' => 'Auditório', 'amount_cents' => 10_000])->json('data');

    $this->post("{$this->api}/expenses/{$expense['id']}/receipt", ['file' => UploadedFile::fake()->createWithContent('pix.exe', 'MZ')], ['Accept' => 'application/json'])->assertUnprocessable();
    $this->post("{$this->api}/expenses/{$expense['id']}/receipt", ['file' => UploadedFile::fake()->createWithContent('pix.pdf', '%PDF comprovante')], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.receipt.name', 'pix.pdf');

    $signed = $this->getJson("{$this->api}/expenses/{$expense['id']}/receipt/url")->assertOk()->json('data.url');
    $this->app['auth']->forgetGuards();

    expect($this->get($signed)->assertOk()->streamedContent())->toBe('%PDF comprovante');

    $this->travel(11)->minutes();
    $this->get($signed)->assertForbidden();
});

it('exporta a planilha em XLSX com as abas e em CSV sem deixar fórmula entrar', function (): void {
    order($this->edition->id, 1, ['status' => 'paid', 'total_cents' => 123_456, 'gateway_fee_cents' => 0]);
    $local = category($this, 'Local');
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $local['id'], 'description' => '=HYPERLINK("http://mal")', 'amount_cents' => 150_050, 'due_on' => '2027-06-01'])->assertCreated();

    $xlsx = $this->get("{$this->api}/export")->assertOk();
    $path = $xlsx->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;
    $zip->open($path);

    $workbook = $zip->getFromName('xl/workbook.xml');
    $resumo = $zip->getFromName('xl/worksheets/sheet1.xml');
    $despesas = $zip->getFromName('xl/worksheets/sheet4.xml');
    $zip->close();

    expect($xlsx->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($workbook)->toContain('name="Resumo"')->toContain('name="Receita por conta"')->toContain('name="Orçamento"')->toContain('name="Despesas"')
        ->and($resumo)->toContain('<v>1234.56</v>')
        ->and($despesas)->toContain('<v>1500.5</v>')->toContain('&apos;=HYPERLINK');

    $csv = $this->get("{$this->api}/export?format=csv")->assertOk()->streamedContent();

    expect($csv)->toStartWith("\u{FEFF}Descrição;Categoria;Fornecedor;\"Valor (R$)\"")
        ->toContain("\"'=HYPERLINK(\"\"http://mal\"\")\";Local;;1500,50;2027-06-01;;\"A pagar\";Não");
});

it('despesa vencida vira pendência da visão geral para quem vê finanças', function (): void {
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => category($this, 'Local')['id'], 'description' => 'Auditório', 'amount_cents' => 10_000, 'due_on' => '2027-05-01'])->assertCreated();

    $pending = collect($this->getJson("/api/v1/editions/{$this->edition->id}/overview")->assertOk()->json('data.pending'))->firstWhere('key', 'expenses_overdue');

    expect($pending['title'])->toBe('1 despesa vencida');

    $volunteer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $volunteer->id, 'role' => EditionRole::Volunteer]);
    Sanctum::actingAs($volunteer);

    expect(collect($this->getJson("/api/v1/editions/{$this->edition->id}/overview")->json('data.pending'))->pluck('key'))->not->toContain('expenses_overdue');
});

it('só organizador e financeiro veem e editam as finanças', function (): void {
    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $this->getJson("{$this->api}/summary")->assertOk();

    foreach ([EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer] as $role) {
        $member = User::factory()->create();
        EditionMember::factory()->for($this->edition)->create(['user_id' => $member->id, 'role' => $role]);
        Sanctum::actingAs($member);

        $this->getJson("{$this->api}/summary")->assertForbidden();
        $this->getJson("{$this->api}/export")->assertForbidden();
        $this->postJson("{$this->api}/expenses", ['finance_category_id' => Str::uuid()->toString(), 'description' => 'Teste', 'amount_cents' => 1])->assertForbidden();
    }
});

it('a próxima edição recebe as categorias e o orçamento, sem as despesas', function (): void {
    $food = category($this, 'Alimentação');
    $this->patchJson("{$this->api}/categories/{$food['id']}", ['budget_cents' => 100_000])->assertOk();
    $this->postJson("{$this->api}/expenses", ['finance_category_id' => $food['id'], 'description' => 'Coffee', 'amount_cents' => 1])->assertCreated();
    $next = Edition::factory()->create();

    resolve(FinanceCloner::class)->clone($this->edition->id, $next->id);

    expect(FinanceCategory::query()->forEdition($next->id)->where('name', 'Alimentação')->value('budget_cents'))->toBe(100_000)
        ->and(FinanceCategory::query()->forEdition($next->id)->count())->toBe(count(FinanceCategory::DEFAULTS))
        ->and(Expense::query()->forEdition($next->id)->count())->toBe(0);
});
