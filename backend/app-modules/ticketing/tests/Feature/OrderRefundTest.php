<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Payments\Gateway\GatewayRequestFailed;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Payments\Models\PaymentRefund;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    $this->batch = TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
    $order = $this->postJson($checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com'], ['name' => 'Bia Lima', 'email' => 'bia@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-1'])->assertCreated()->json('data');

    $headers = ['X-Order-Token' => $order['access_token']];
    $this->postJson("{$checkout}/{$order['id']}/payment", ['method' => 'pix'], $headers)->assertOk();
    FakeGateway::pay(PaymentCharge::query()->forEdition($this->edition->id)->sole()->gateway_charge_id);
    $this->travel(6)->seconds();
    $this->getJson("{$checkout}/{$order['id']}/payment", $headers)->assertJsonPath('data.order_status', 'paid');

    $this->order = Order::query()->forEdition($this->edition->id)->findOrFail($order['id']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($this->organizer);
    $this->orderUrl = "/api/v1/editions/{$this->edition->id}/ticketing/orders/{$this->order->id}";
});

function refundEmails(string $editionId): int
{
    return EmailDelivery::query()->forEdition($editionId)->where('template', EmailTemplate::OrderRefunded->value)->count();
}

it('reembolso total cancelando os ingressos devolve as vagas, avisa quem comprou e audita', function (): void {
    $this->getJson($this->orderUrl)
        ->assertJsonPath('data.gateway_fee.amount', FakeGateway::fee(30_000))
        ->assertJsonPath('data.refunded.amount', 0);
    expect($this->batch->refresh()->allocated)->toBe(2);

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'note' => 'Pediu pelo Instagram', 'cancel_tickets' => true])
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.amount.amount', 30_000)
        ->assertJsonPath('data.reason_label', 'Pedido de quem comprou')
        ->assertJsonPath('data.note', 'Pediu pelo Instagram')
        ->assertJsonCount(2, 'data.cancelled_item_ids');

    $this->getJson($this->orderUrl)
        ->assertJsonPath('data.status', 'refunded')
        ->assertJsonPath('data.refunded.amount', 30_000)
        ->assertJsonPath('data.items.0.cancelled_at', '2027-06-10T12:00:06Z')
        ->assertJsonPath('data.items.1.cancelled_at', '2027-06-10T12:00:06Z');

    $email = EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::OrderRefunded->value)->sole();
    $audit = AuditLog::query()->where('action', 'ticketing.order_refunded')->sole();

    expect($this->batch->refresh()->allocated)->toBe(0)
        ->and(PaymentCharge::query()->forEdition($this->edition->id)->sole())->status->toBe(ChargeStatus::Refunded)->refunded_cents->toBe(30_000)
        ->and($email->to_email)->toBe('ana@exemplo.com')
        ->and($email->variables)->toMatchArray(['valor' => 'R$ 300,00', 'pedido' => "#{$this->order->number}", 'ingressos' => '2 ingressos foram cancelados.'])
        ->and($audit->actor_id)->toBe($this->organizer->id)
        ->and($audit->after)->toMatchArray(['amount_cents' => 30_000, 'reason' => 'buyer_request', 'note' => 'Pediu pelo Instagram', 'cancelled_tickets' => 2]);

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount_cents.0', 'Este pedido já foi reembolsado por inteiro.');
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'amount_cents' => 100])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount_cents.0', 'Este pedido não tem pagamento confirmado para reembolsar.');
});

it('reembolso parcial de um ingresso mantém o pedido pago, e o que passa do saldo é recusado', function (): void {
    $first = OrderItem::query()->forEdition($this->edition->id)->where('order_id', $this->order->id)->orderBy('id')->firstOrFail();

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true, 'item_ids' => [$first->id]])
        ->assertCreated()
        ->assertJsonPath('data.amount.amount', 15_000);

    $this->getJson($this->orderUrl)->assertJsonPath('data.status', 'paid')->assertJsonPath('data.refunded.amount', 15_000);
    expect($this->batch->refresh()->allocated)->toBe(1);

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'amount_cents' => 20_000])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount_cents.0', 'O valor passa do que ainda pode ser reembolsado: R$ 150,00.');
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true, 'item_ids' => [$first->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors.item_ids.0', 'Escolha ingressos deste pedido que ainda não foram cancelados.');
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'other', 'item_ids' => [$first->id]])
        ->assertStatus(422)
        ->assertJsonPath('errors.item_ids.0', 'Escolher ingressos só vale quando eles vão ser cancelados.');

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'event_changed'])->assertCreated()->assertJsonPath('data.amount.amount', 15_000);

    $this->getJson($this->orderUrl)->assertJsonPath('data.status', 'refunded')->assertJsonPath('data.items.1.cancelled_at', null);
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'duplicate', 'amount_cents' => 1])->assertStatus(422);
    $this->getJson("{$this->orderUrl}/refunds")->assertOk()->assertJsonCount(2, 'data');
    expect(collect(FakeGateway::$calls)->where('method', 'refund')->count())->toBe(2)
        ->and(refundEmails($this->edition->id))->toBe(2);
});

it('o gateway recusando o estorno não devolve nada, não cancela ingresso e libera o saldo', function (): void {
    FakeGateway::$refundFailure = GatewayRequestFailed::REJECTED;

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount_cents.0', 'O gateway recusou o reembolso. Nada foi devolvido; confira o pagamento no painel do gateway.');

    expect(PaymentRefund::query()->forEdition($this->edition->id)->sole())->status->toBe(RefundStatus::Failed)
        ->and(PaymentCharge::query()->forEdition($this->edition->id)->sole()->refunded_cents)->toBe(0)
        ->and($this->batch->refresh()->allocated)->toBe(2)
        ->and(refundEmails($this->edition->id))->toBe(0)
        ->and(AuditLog::query()->where('action', 'payments.refund_failed')->count())->toBe(1);
    $this->getJson($this->orderUrl)->assertJsonPath('data.status', 'paid')->assertJsonPath('data.items.0.cancelled_at', null);

    FakeGateway::$refundFailure = null;
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true])->assertCreated();
    $this->getJson($this->orderUrl)->assertJsonPath('data.status', 'refunded');
});

it('o gateway fora do ar deixa o reembolso a conferir, sem permitir outro, até a organização registrar o resultado', function (): void {
    FakeGateway::$refundFailure = GatewayRequestFailed::UNAVAILABLE;

    $refund = $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'unconfirmed')
        ->assertJsonPath('data.failure_reason', 'O gateway não respondeu. Confira no painel do gateway se o estorno saiu e registre o resultado aqui.')
        ->json('data.id');

    FakeGateway::$refundFailure = null;
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])
        ->assertStatus(422)
        ->assertJsonPath('errors.amount_cents.0', 'Este pagamento já foi reembolsado.');
    expect($this->batch->refresh()->allocated)->toBe(2)->and(refundEmails($this->edition->id))->toBe(0);

    $this->postJson("/api/v1/editions/{$this->edition->id}/ticketing/reconciliation/run")->assertOk()->assertJsonPath('data.new', 1);
    $this->getJson("/api/v1/editions/{$this->edition->id}/ticketing/reconciliation")
        ->assertJsonPath('data.0.kind', 'refund_unconfirmed')
        ->assertJsonPath('data.0.order_id', $this->order->id);

    $this->postJson("{$this->orderUrl}/refunds/{$refund}/settle", ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
    $this->postJson("{$this->orderUrl}/refunds/{$refund}/settle", ['completed' => true])
        ->assertStatus(422)
        ->assertJsonPath('errors.refund.0', 'Só reembolsos a conferir podem ser registrados manualmente.');

    $this->getJson($this->orderUrl)->assertJsonPath('data.status', 'refunded')->assertJsonPath('data.refunded.amount', 30_000);
    expect($this->batch->refresh()->allocated)->toBe(0)
        ->and(refundEmails($this->edition->id))->toBe(1)
        ->and(collect(FakeGateway::$calls)->where('method', 'refund')->count())->toBe(1);

    $this->postJson("/api/v1/editions/{$this->edition->id}/ticketing/reconciliation/run")->assertJsonPath('data.resolved', 1)->assertJsonPath('data.open', 0);
});

it('estorno a conferir registrado como não realizado libera o saldo para tentar de novo', function (): void {
    FakeGateway::$refundFailure = GatewayRequestFailed::UNAVAILABLE;
    $refund = $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])->assertStatus(202)->json('data.id');
    FakeGateway::$refundFailure = null;

    $this->postJson("{$this->orderUrl}/refunds/{$refund}/settle", ['completed' => false])->assertOk()->assertJsonPath('data.status', 'failed');
    expect(PaymentCharge::query()->forEdition($this->edition->id)->sole()->refunded_cents)->toBe(0);

    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])->assertCreated()->assertJsonPath('data.status', 'completed');
});

it('financeiro reembolsa, outros papéis não, e pedido gratuito não tem o que reembolsar', function (): void {
    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])->assertForbidden();
    $this->getJson("/api/v1/editions/{$this->edition->id}/ticketing/reconciliation")->assertForbidden();

    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request', 'amount_cents' => 1_000])->assertCreated();

    Order::query()->forEdition($this->edition->id)->whereKey($this->order->id)->update(['total_cents' => 0]);
    $this->postJson("{$this->orderUrl}/refunds", ['reason' => 'buyer_request'])
        ->assertStatus(422)
        ->assertJsonPath('errors.order.0', 'Pedido sem pagamento: para tirar o ingresso, cancele o pedido.');
});
