<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/ticketing";
});

function pixOrder(object $test, string $key): array
{
    $checkout = "/api/v1/checkout/editions/{$test->edition->id}/orders";
    $order = $test->postJson($checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => "{$key}@exemplo.com", 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $test->type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => "{$key}@exemplo.com"]]]],
    ], ['Idempotency-Key' => $key])->assertCreated()->json('data');
    $test->postJson("{$checkout}/{$order['id']}/payment", ['method' => 'pix'], ['X-Order-Token' => $order['access_token']])->assertOk();

    return [$order['id'], PaymentCharge::query()->forEdition($test->edition->id)->where('reference', $order['id'])->sole()->gateway_charge_id];
}

it('Pix pago depois de a reserva expirar vira divergência, alerta e some depois do reembolso', function (): void {
    Log::spy();
    [$orderId, $chargeId] = pixOrder($this, 'atrasada');

    $this->travel(31)->minutes();
    $this->artisan('ticketing:expire-orders');
    FakeGateway::$charges[$chargeId]['status'] = ChargeStatus::Paid;

    $this->artisan('ticketing:reconcile')->expectsOutputToContain('1 divergências abertas (1 novas, 0 resolvidas)');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->base}/reconciliation")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.kind', 'charge_without_order')
        ->assertJsonPath('data.0.order_id', $orderId)
        ->assertJsonPath('data.0.details.order_status', 'expired')
        ->assertJsonPath('data.0.details.refundable_cents', 15_000)
        ->assertJsonPath('data.0.advice', 'O pagamento chegou depois de a reserva expirar ou de o pedido ser cancelado. Reembolse com o motivo "Pago depois de a reserva expirar".');
    $this->getJson("/api/v1/editions/{$this->edition->id}/overview")
        ->assertJsonFragment(['key' => 'reconciliation_issues', 'title' => '1 divergência na conciliação de pagamentos']);
    Log::shouldHaveReceived('warning')->with('ticketing.reconciliation_issue', Mockery::on(fn (array $context): bool => $context['kind'] === 'charge_without_order'))->once();

    $this->postJson("{$this->base}/orders/{$orderId}/refunds", ['reason' => 'paid_after_expiry', 'cancel_tickets' => true])
        ->assertStatus(422)
        ->assertJsonPath('errors.cancel_tickets.0', 'Os ingressos de um pedido expirado já não valem; reembolse sem cancelar ingressos.');
    $this->postJson("{$this->base}/orders/{$orderId}/refunds", ['reason' => 'paid_after_expiry'])->assertCreated()->assertJsonPath('data.amount.amount', 15_000);

    $this->postJson("{$this->base}/reconciliation/run")->assertJsonPath('data.open', 0)->assertJsonPath('data.resolved', 1);
    $this->getJson("{$this->base}/reconciliation?status=resolved")
        ->assertJsonPath('data.0.resolution', 'A divergência não apareceu mais na conferência.')
        ->assertJsonPath('data.0.resolved_by', null);
    $this->getJson("{$this->base}/orders/{$orderId}")->assertJsonPath('data.status', 'expired')->assertJsonPath('data.refunded.amount', 15_000);
});

it('acha pedido pago sem cobrança, pagamento em dobro e contestação, e respeita o que a organização resolveu', function (): void {
    [$paidId, $paidCharge] = pixOrder($this, 'paga');
    FakeGateway::pay($paidCharge);
    [$ghostId] = pixOrder($this, 'fantasma');
    Order::query()->forEdition($this->edition->id)->whereKey($ghostId)->update(['status' => 'paid', 'paid_at' => now()]);
    PaymentCharge::query()->forEdition($this->edition->id)->where('reference', $ghostId)->update(['status' => 'cancelled']);

    $this->artisan('ticketing:reconcile', ['--edition' => $this->edition->id])->assertSuccessful();

    $paid = PaymentCharge::query()->forEdition($this->edition->id)->where('reference', $paidId)->sole();
    PaymentCharge::create([...collect($paid->getAttributes())->except(['id', 'created_at', 'updated_at'])->all(), 'gateway_charge_id' => 'pix_segunda_vez']);
    FakeGateway::$charges[$paidCharge]['status'] = ChargeStatus::Disputed;
    $this->travel(1)->days();

    Sanctum::actingAs($this->organizer);
    $this->postJson("{$this->base}/reconciliation/run")->assertOk()->assertJsonPath('data.new', 2)->assertJsonPath('data.open', 3);

    $issues = collect($this->getJson("{$this->base}/reconciliation")->json('data'));
    expect($issues->pluck('kind')->sort()->values()->all())->toBe(['disputed', 'duplicate_payment', 'paid_without_charge'])
        ->and($issues->firstWhere('kind', 'duplicate_payment')['order_id'])->toBe($paidId)
        ->and($issues->firstWhere('kind', 'paid_without_charge')['order_id'])->toBe($ghostId);

    $ghost = collect($this->getJson("{$this->base}/reconciliation")->json('data'))->firstWhere('kind', 'paid_without_charge');
    $this->postJson("{$this->base}/reconciliation/{$ghost['id']}/resolve", [])->assertStatus(422)->assertJsonPath('errors.resolution.0', 'Conte o que foi feito para resolver a divergência.');
    $this->postJson("{$this->base}/reconciliation/{$ghost['id']}/resolve", ['resolution' => 'Pago em dinheiro na secretaria'])
        ->assertOk()
        ->assertJsonPath('data.resolved_by', $this->organizer->id);

    $this->postJson("{$this->base}/reconciliation/run")->assertJsonPath('data.open', 2)->assertJsonPath('data.new', 0);
    $this->postJson("{$this->base}/reconciliation/{$ghost['id']}/resolve", ['resolution' => 'de novo'])->assertNotFound();
});
