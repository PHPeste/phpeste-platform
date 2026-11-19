<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Payments\Models\WebhookEventRecord;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";

    $order = $this->postJson($checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-1'])->assertCreated()->json('data');

    $this->orderUrl = "{$checkout}/{$order['id']}";
    $this->orderId = $order['id'];
    $this->headers = ['X-Order-Token' => $order['access_token']];
    $this->postJson("{$this->orderUrl}/payment", ['method' => 'pix'], $this->headers)->assertOk();
    $this->chargeId = PaymentCharge::query()->forEdition($this->edition->id)->sole()->gateway_charge_id;
});

function gatewayEvent(object $test, array $payload): TestResponse
{
    $body = (string) json_encode($payload);

    return $test->call('POST', '/api/v1/webhooks/payments/'.str_repeat('a', 48), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => FakeGateway::sign($body, 'segredo-webhook')], $body);
}

function paidTransitions(string $orderId): int
{
    return AuditLog::query()->where('action', 'ticketing.order_status_changed')->where('subject_id', $orderId)->get()
        ->filter(fn (AuditLog $log): bool => ($log->after['status'] ?? null) === 'paid')
        ->count();
}

function inbox(string $editionId, string $eventId): WebhookEventRecord
{
    return WebhookEventRecord::query()->forEdition($editionId)->where('event_id', $eventId)->sole();
}

it('o webhook confirma o pedido, e o mesmo evento repetido não confirma de novo', function (): void {
    FakeGateway::pay($this->chargeId);

    gatewayEvent($this, ['id' => 'evt_pago', 'type' => 'payment.paid', 'charge_id' => $this->chargeId, 'status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.duplicate', false);

    $this->getJson($this->orderUrl, $this->headers)->assertJsonPath('data.status', 'paid');
    expect(inbox($this->edition->id, 'evt_pago'))
        ->status->toBe(WebhookEventStatus::Processed)
        ->result->toBe('Cobrança: pago.')
        ->attempts->toBe(1);

    $fetches = collect(FakeGateway::$calls)->where('method', 'fetch')->count();

    gatewayEvent($this, ['id' => 'evt_pago', 'type' => 'payment.paid', 'charge_id' => $this->chargeId, 'status' => 'paid'])
        ->assertOk()
        ->assertJsonPath('data.duplicate', true);
    gatewayEvent($this, ['id' => 'evt_confirmado', 'type' => 'payment.confirmed', 'charge_id' => $this->chargeId, 'status' => 'paid'])->assertOk();

    expect(paidTransitions($this->orderId))->toBe(1)
        ->and(collect(FakeGateway::$calls)->where('method', 'fetch')->count())->toBe($fetches + 1)
        ->and(WebhookEventRecord::query()->forEdition($this->edition->id)->count())->toBe(2);
});

it('o status vem do gateway, não do corpo: evento falso ou fora de ordem não mexe no pedido', function (): void {
    gatewayEvent($this, ['id' => 'evt_mentira', 'type' => 'payment.paid', 'charge_id' => $this->chargeId, 'status' => 'paid'])->assertOk();
    $this->getJson($this->orderUrl, $this->headers)->assertJsonPath('data.status', 'awaiting_payment');
    expect(inbox($this->edition->id, 'evt_mentira')->result)->toBe('Cobrança: aguardando pagamento.');

    FakeGateway::pay($this->chargeId);
    gatewayEvent($this, ['id' => 'evt_pago', 'type' => 'payment.paid', 'charge_id' => $this->chargeId])->assertOk();
    gatewayEvent($this, ['id' => 'evt_criado', 'type' => 'payment.created', 'charge_id' => $this->chargeId, 'status' => 'pending'])->assertOk();

    $this->getJson($this->orderUrl, $this->headers)->assertJsonPath('data.status', 'paid');
    expect(PaymentCharge::query()->forEdition($this->edition->id)->sole()->status)->toBe(ChargeStatus::Paid);
});

it('evento de cobrança desconhecida fica ignorado e não trava os próximos', function (): void {
    gatewayEvent($this, ['id' => 'evt_outro', 'type' => 'payment.paid', 'charge_id' => 'pix_de_outro_sistema', 'status' => 'paid'])->assertOk();
    gatewayEvent($this, ['id' => 'evt_ping', 'type' => 'ping'])->assertOk();

    FakeGateway::pay($this->chargeId);
    gatewayEvent($this, ['id' => 'evt_pago', 'type' => 'payment.paid', 'charge_id' => $this->chargeId])->assertOk();

    expect(inbox($this->edition->id, 'evt_outro'))->status->toBe(WebhookEventStatus::Ignored)->result->toBe('Cobrança não encontrada na plataforma.')
        ->and(inbox($this->edition->id, 'evt_ping'))->status->toBe(WebhookEventStatus::Ignored)->result->toBe('Evento sem cobrança associada.')
        ->and(inbox($this->edition->id, 'evt_pago')->status)->toBe(WebhookEventStatus::Processed);
    $this->getJson($this->orderUrl, $this->headers)->assertJsonPath('data.status', 'paid');
});

it('falha no gateway tenta de novo com espera crescente, alerta a organização e pode ser reprocessada', function (): void {
    $charge = FakeGateway::$charges[$this->chargeId];
    unset(FakeGateway::$charges[$this->chargeId]);

    gatewayEvent($this, ['id' => 'evt_pago', 'type' => 'payment.paid', 'charge_id' => $this->chargeId])->assertOk();

    expect(inbox($this->edition->id, 'evt_pago'))
        ->status->toBe(WebhookEventStatus::Failed)
        ->attempts->toBe(1)
        ->next_attempt_at->toIso8601ZuluString()->toBe('2027-06-10T12:01:00Z');

    $this->artisan('payments:retry-webhooks')->expectsOutput('0 webhooks devolvidos para a fila.');

    foreach ([1, 5, 15, 60] as $minutes) {
        $this->travel($minutes)->minutes();
        $this->artisan('payments:retry-webhooks')->expectsOutput('1 webhooks devolvidos para a fila.');
    }

    $event = inbox($this->edition->id, 'evt_pago');
    expect($event)->status->toBe(WebhookEventStatus::Failed)->attempts->toBe(5)->next_attempt_at->toBeNull()
        ->and($event->result)->toBe('Cobrança não encontrada nesta conta do gateway.');

    $this->travel(2)->hours();
    $this->artisan('payments:retry-webhooks')->expectsOutput('0 webhooks devolvidos para a fila.');

    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);

    $this->getJson("/api/v1/editions/{$this->edition->id}/overview")
        ->assertJsonFragment(['key' => 'webhooks_failed', 'title' => '1 aviso do gateway com falha']);
    $this->getJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events?status=failed")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event_id', 'evt_pago')
        ->assertJsonPath('data.0.can_reprocess', true)
        ->assertJsonMissingPath('data.0.payload');

    FakeGateway::$charges[$this->chargeId] = [...$charge, 'status' => ChargeStatus::Paid];

    $this->postJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events/{$event->id}/reprocess")
        ->assertOk()
        ->assertJsonPath('data.status', 'processed')
        ->assertJsonPath('data.attempts', 1);
    $this->postJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events/{$event->id}/reprocess")
        ->assertStatus(422)
        ->assertJsonPath('errors.status.0', 'Só eventos com falha ou ignorados podem ser reprocessados.');

    expect(paidTransitions($this->orderId))->toBe(1)
        ->and(AuditLog::query()->where('action', 'payments.webhook_reprocessed')->sole()->actor_id)->toBe($organizer->id);
    $this->getJson("/api/v1/editions/{$this->edition->id}/overview")->assertJsonMissing(['key' => 'webhooks_failed']);
});
