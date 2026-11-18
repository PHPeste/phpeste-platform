<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Actions\RefundCharge;
use PHPeste\Payments\Contracts\AccountUsage;
use PHPeste\Payments\Contracts\PaymentAccounts;
use PHPeste\Payments\Data\CardPayment;
use PHPeste\Payments\Data\ChargeRequest;
use PHPeste\Payments\Data\Payer;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Gateway\GatewayRegistry;
use PHPeste\Payments\Models\PaymentAccount;

const API_KEY = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjUxZmE1abcd';
const WEBHOOK_SECRET = 'whsec_super_secreto_123';

beforeEach(function (): void {
    FakeGateway::reset();
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/payments/accounts";
    Sanctum::actingAs($this->organizer);
});

function accountPayload(array $overrides = []): array
{
    return [
        'driver' => 'fake',
        'environment' => 'sandbox',
        'api_key' => API_KEY,
        'webhook_secret' => WEBHOOK_SECRET,
        'holder_type' => 'individual',
        'holder_name' => 'Fulana de Tal, responsável financeira da PHPeste 2027',
        'holder_document' => '529.982.247-25',
        'methods' => ['pix', 'card'],
        'max_installments' => 3,
        ...$overrides,
    ];
}

function webhook(object $test, string $url, string $body, string $secret = WEBHOOK_SECRET): TestResponse
{
    return $test->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => FakeGateway::sign($body, $secret)], $body);
}

it('cadastra a conta com titular CPF e nunca devolve as credenciais em texto', function (): void {
    $response = $this->postJson($this->base, accountPayload())
        ->assertCreated()
        ->assertJsonPath('data.credentials_hint', '••••abcd')
        ->assertJsonPath('data.holder', ['type' => 'individual', 'type_label' => 'Pessoa física (CPF)', 'name' => 'Fulana de Tal, responsável financeira da PHPeste 2027', 'document' => '***.***.247-**'])
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.methods.0.key', 'pix');

    $list = $this->getJson($this->base)->assertOk()->assertJsonPath('data.drivers', [['key' => 'fake', 'label' => 'Gateway de teste']]);

    foreach ([$response->getContent(), $list->getContent()] as $body) {
        expect($body)->not->toContain(API_KEY)
            ->not->toContain(mb_substr(API_KEY, 0, -4))
            ->not->toContain(WEBHOOK_SECRET)
            ->not->toContain('52998224725');
    }

    $raw = DB::table('payment_accounts')->value('credentials');
    expect($raw)->not->toContain(API_KEY)->not->toContain(WEBHOOK_SECRET)
        ->and(PaymentAccount::query()->forEdition($this->edition->id)->sole()->credentials['api_key'])->toBe(API_KEY);
});

it('aceita CNPJ de pessoa jurídica e valida documento, métodos e driver', function (): void {
    $this->postJson($this->base, accountPayload(['holder_type' => 'company', 'holder_name' => 'Associação PHP Paraíba', 'holder_document' => '11.222.333/0001-81']))
        ->assertCreated()->assertJsonPath('data.holder.document', '**.***.333/0001-**');

    $this->postJson($this->base, accountPayload(['holder_type' => 'company', 'holder_document' => '529.982.247-25']))
        ->assertStatus(422)->assertJsonPath('errors.holder_document.0', 'Informe um CNPJ válido.');
    $this->postJson($this->base, accountPayload(['holder_document' => '111.111.111-11']))
        ->assertStatus(422)->assertJsonPath('errors.holder_document.0', 'Informe um CPF válido.');
    $this->postJson($this->base, accountPayload(['methods' => []]))->assertStatus(422)->assertJsonPath('errors.methods.0', 'Escolha pelo menos um método de pagamento.');
    $this->postJson($this->base, accountPayload(['driver' => 'stripe']))->assertStatus(422)->assertJsonValidationErrors(['driver']);
});

it('valida as chaves no gateway e fica pronta só depois de receber o webhook assinado', function (): void {
    $id = $this->postJson($this->base, accountPayload(['api_key' => 'invalid_key_0000']))->json('data.id');
    $this->postJson("{$this->base}/{$id}/verify")->assertStatus(422)->assertJsonPath('errors.api_key.0', 'O gateway recusou a chave de API.');

    $id = $this->postJson($this->base, accountPayload())->json('data.id');
    $this->postJson("{$this->base}/{$id}/verify")->assertOk()->assertJsonPath('data.verified_at', now()->utc()->toIso8601ZuluString())->assertJsonPath('data.ready', false);
    expect(resolve(PaymentAccounts::class)->ready($this->edition->id))->toBeFalse();

    $url = parse_url((string) $this->getJson($this->base)->json('data.active.webhook_url'), PHP_URL_PATH);
    $this->app['auth']->forgetGuards();

    webhook($this, $url, '{"id":"evt_1","type":"ping"}', 'segredo-errado')->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
    $this->postJson('/api/v1/webhooks/payments/'.str_repeat('z', 48), [])->assertNotFound();
    expect(resolve(PaymentAccounts::class)->ready($this->edition->id))->toBeFalse();

    webhook($this, $url, '{"id":"evt_1","type":"ping"}')->assertOk()->assertJsonPath('data.event_id', 'evt_1');
    webhook($this, $url, '{"id":"evt_2","type":"ping"}')->assertOk();

    expect(resolve(PaymentAccounts::class)->ready($this->edition->id))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'payments.webhook_verified')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'payments.credentials_verified')->sole()->actor_id)->toBe($this->organizer->id);

    $this->getJson("/api/v1/public/editions/{$this->edition->id}/payments/receiver")
        ->assertOk()
        ->assertJsonPath('data', ['holder_name' => 'Fulana de Tal, responsável financeira da PHPeste 2027', 'holder_type' => 'individual', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'pix_expiration_minutes' => 30, 'sandbox' => true, 'ready' => true]);
});

it('trocar a conta cria uma nova, guarda a antiga só para consulta e audita sem expor a chave', function (): void {
    $old = $this->postJson($this->base, accountPayload())->json('data.id');
    $new = $this->postJson($this->base, accountPayload(['api_key' => 'nova_chave_produção_wxyz', 'environment' => 'production', 'holder_type' => 'company', 'holder_name' => 'Associação PHP Paraíba', 'holder_document' => '11.222.333/0001-81']))
        ->assertCreated()->json('data.id');

    $this->getJson($this->base)
        ->assertJsonPath('data.active.id', $new)
        ->assertJsonPath('data.active.credentials_hint', '••••wxyz')
        ->assertJsonPath('data.previous.0.id', $old)
        ->assertJsonPath('data.previous.0.active', false)
        ->assertJsonPath('data.previous.0.webhook_url', null);

    $this->patchJson("{$this->base}/{$old}", ['holder_name' => 'Outro nome'])->assertStatus(422)->assertJsonPath('errors.account.0', 'Esta conta foi substituída e fica só para consulta e reembolsos.');
    $this->postJson("{$this->base}/{$old}/verify")->assertStatus(422);
    $this->patchJson("{$this->base}/{$new}", ['holder_name' => 'Associação PHP PB', 'max_installments' => 6])->assertOk()->assertJsonPath('data.holder.name', 'Associação PHP PB');

    $log = AuditLog::query()->where('action', 'payments.account_changed')->where('subject_id', $new)->sole();
    expect($log->before['account_id'])->toBe($old)
        ->and($log->after['credentials_hint'])->toBe('••••wxyz')
        ->and(json_encode([$log->before, $log->after]))->not->toContain('nova_chave')->not->toContain(API_KEY);
});

it('trocar a conta que já recebeu pedidos pagos exige confirmação', function (): void {
    $this->app->instance(AccountUsage::class, new class implements AccountUsage
    {
        public function paidOrders(string $editionId, string $accountId): int
        {
            return 3;
        }
    });

    $this->postJson($this->base, accountPayload())->assertCreated();
    $this->postJson($this->base, accountPayload(['api_key' => 'outra_chave_1111']))
        ->assertStatus(422)
        ->assertJsonPath('errors.confirm.0', 'Esta conta já recebeu 3 pedidos pagos. A conta atual fica guardada só para reembolsos desses pedidos, e as próximas vendas caem na conta nova. Confirme para trocar.');
    expect(PaymentAccount::query()->forEdition($this->edition->id)->count())->toBe(1);

    $this->postJson($this->base, accountPayload(['api_key' => 'outra_chave_1111', 'confirm' => true]))->assertCreated();
});

it('reembolso de cobrança paga na conta antiga continua funcionando depois da troca', function (): void {
    $old = PaymentAccount::query()->forEdition($this->edition->id)->findOrFail($this->postJson($this->base, accountPayload())->json('data.id'));
    $gateway = resolve(GatewayRegistry::class)->driver('fake');
    $charge = $gateway->chargeCard($old->gatewayCredentials(), new ChargeRequest('pedido-1', 15_000, 'PHPeste 2027', new Payer('Ana', 'ana@exemplo.com', '52998224725'), now()->addMinutes(30)), new CardPayment('tok_ok', 1));

    $this->postJson($this->base, accountPayload(['api_key' => 'nova_chave_2222']))->assertCreated();

    $refund = resolve(RefundCharge::class)->handle($this->edition->id, $old->id, $charge->chargeId, 15_000);

    expect($refund->completed)->toBeTrue()
        ->and(collect(FakeGateway::$calls)->firstWhere('method', 'refund')['api_key'])->toBe(API_KEY);
});

it('só a organização mexe no gateway', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);

    $this->getJson($this->base)->assertForbidden();
    $this->postJson($this->base, accountPayload())->assertForbidden();
});

it('regrava as credenciais com a chave nova na rotação da APP_KEY', function (): void {
    $this->postJson($this->base, accountPayload())->assertCreated();
    $oldKey = config('app.key');
    $before = DB::table('payment_accounts')->value('credentials');

    $newKey = 'base64:'.base64_encode(random_bytes(32));
    config(['app.key' => $newKey, 'app.previous_keys' => [$oldKey]]);
    $this->app->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');
    PaymentAccount::encryptUsing(null);

    $this->artisan('payments:reencrypt-credentials')->expectsOutput('1 contas regravadas.')->assertSuccessful();

    $after = DB::table('payment_accounts')->value('credentials');
    config(['app.previous_keys' => []]);
    $this->app->forgetInstance('encrypter');
    Crypt::clearResolvedInstance('encrypter');

    expect($after)->not->toBe($before)
        ->and(PaymentAccount::query()->forEdition($this->edition->id)->sole()->credentials['api_key'])->toBe(API_KEY);
});
