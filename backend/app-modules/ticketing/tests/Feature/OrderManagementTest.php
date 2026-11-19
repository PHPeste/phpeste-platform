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
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027', 'timezone' => 'America/Fortaleza']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    $this->batch = TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $this->checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
    $this->organizer = User::factory()->create(['name' => 'Rita Organizadora']);
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->api = "/api/v1/editions/{$this->edition->id}/ticketing/orders";
});

function awaitingOrder(object $test, string $buyer = 'Ana Souza', string $key = 'pedido-1'): Order
{
    $order = $test->postJson($test->checkout, [
        'buyer' => ['name' => $buyer, 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $test->type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'ana@exemplo.com'], ['name' => 'Bia Lima', 'email' => 'bia@exemplo.com']]]],
    ], ['Idempotency-Key' => $key])->assertCreated()->json('data');

    $test->postJson("{$test->checkout}/{$order['id']}/payment", ['method' => 'pix'], ['X-Order-Token' => $order['access_token']])->assertOk();

    return Order::query()->forEdition($test->edition->id)->findOrFail($order['id']);
}

function memberAs(object $test, EditionRole $role): User
{
    $user = User::factory()->create();
    EditionMember::factory()->for($test->edition)->create(['user_id' => $user->id, 'role' => $role]);

    return $user;
}

it('o detalhe mostra a cobrança em andamento no gateway', function (): void {
    $order = awaitingOrder($this);
    Sanctum::actingAs($this->organizer);

    $this->getJson("{$this->api}/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'awaiting_payment')
        ->assertJsonPath('meta.payment.method', 'pix')
        ->assertJsonPath('meta.payment.status', 'pending');
});

it('marcar como pago exige justificativa, emite os ingressos, cancela a cobrança e vai para a auditoria e o histórico', function (): void {
    $order = awaitingOrder($this);
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->api}/{$order->id}/mark-paid", [])->assertUnprocessable()->assertJsonValidationErrors(['justification']);
    $this->postJson("{$this->api}/{$order->id}/mark-paid", ['justification' => 'curto'])->assertUnprocessable()->assertJsonValidationErrors(['justification']);

    $this->postJson("{$this->api}/{$order->id}/mark-paid", ['justification' => 'Transferência para a conta da comunidade, comprovante no drive'])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.paid_at', '2027-06-10T12:00:00Z');

    expect(Ticket::query()->forEdition($this->edition->id)->count())->toBe(2)
        ->and(PaymentCharge::query()->forEdition($this->edition->id)->sole()->status)->toBe(ChargeStatus::Cancelled);

    $audit = AuditLog::query()->where('action', 'ticketing.order_status_changed')->where('after->status', 'paid')->sole();
    expect($audit->actor_id)->toBe($this->organizer->id)
        ->and($audit->after['reason'])->toBe('manual: Transferência para a conta da comunidade, comprovante no drive');

    $history = $this->getJson("{$this->api}/{$order->id}/history")->assertOk()->json('data');
    expect(array_column($history, 'kind'))->toBe(['created', 'payment_started', 'marked_paid'])
        ->and($history[1]['detail'])->toBe('Pix')
        ->and($history[2])->toMatchArray([
            'title' => 'Marcado como pago manualmente',
            'detail' => 'Transferência para a conta da comunidade, comprovante no drive',
            'actor' => ['id' => $this->organizer->id, 'name' => 'Rita Organizadora'],
        ]);

    $this->postJson("{$this->api}/{$order->id}/mark-paid", ['justification' => 'De novo, por engano no clique'])
        ->assertUnprocessable()->assertJsonValidationErrors(['order']);
});

it('só quem configura a edição marca como pago ou reenvia cobrança; o financeiro vê, exporta e o histórico', function (): void {
    $order = awaitingOrder($this);
    Sanctum::actingAs(memberAs($this, EditionRole::Finance));

    $this->postJson("{$this->api}/{$order->id}/mark-paid", ['justification' => 'Recebido em dinheiro na secretaria'])->assertForbidden();
    $this->postJson("{$this->api}/{$order->id}/payment-reminder")->assertForbidden();
    $this->getJson("{$this->api}/{$order->id}/history")->assertOk();
    $this->get("{$this->api}/export")->assertOk();

    Sanctum::actingAs(memberAs($this, EditionRole::CheckIn));
    $this->get("{$this->api}/export")->assertForbidden();
    $this->getJson("{$this->api}/{$order->id}/history")->assertForbidden();

    expect($order->fresh()->status->value)->toBe('awaiting_payment');
});

it('reenviar a cobrança manda o link do pedido para quem comprou, só enquanto a reserva vale', function (): void {
    $order = awaitingOrder($this);
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->api}/{$order->id}/payment-reminder")->assertAccepted();

    $email = EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::PixReminder->value)->sole();
    expect($email->to_email)->toBe('ana@exemplo.com')
        ->and($email->action_url)->toEndWith("/checkout/pedido/{$order->id}")
        ->and(AuditLog::query()->where('action', 'ticketing.payment_reminder_sent')->sole()->actor_id)->toBe($this->organizer->id);

    $this->getJson("{$this->api}/{$order->id}/history")->assertJsonPath('data.2.kind', 'reminder');

    $this->travel(31)->minutes();
    $this->postJson("{$this->api}/{$order->id}/payment-reminder")->assertUnprocessable()->assertJsonValidationErrors(['order']);
});

it('não reenvia cobrança de pedido pago nem marca como pago pedido cancelado', function (): void {
    $order = awaitingOrder($this);
    Sanctum::actingAs($this->organizer);

    $this->postJson("{$this->api}/{$order->id}/cancel")->assertOk();
    $this->postJson("{$this->api}/{$order->id}/mark-paid", ['justification' => 'Pagou depois do cancelamento'])->assertUnprocessable();
    $this->postJson("{$this->api}/{$order->id}/payment-reminder")->assertUnprocessable();

    $history = $this->getJson("{$this->api}/{$order->id}/history")->json('data');
    expect(end($history))->toMatchArray(['kind' => 'cancelled', 'detail' => 'Pela organização']);
});

it('exporta os pedidos com os filtros da lista, valores em reais e sem deixar virar fórmula', function (): void {
    $first = awaitingOrder($this, '=HYPERLINK("http://mal.example")', 'pedido-1');
    awaitingOrder($this, 'Caio Reis', 'pedido-2');
    Sanctum::actingAs($this->organizer);
    $this->postJson("{$this->api}/{$first->id}/mark-paid", ['justification' => 'Recebido em dinheiro na secretaria'])->assertOk();

    $response = $this->get("{$this->api}/export?status=paid");
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $lines = explode("\n", mb_trim($response->streamedContent()));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toStartWith("\xEF\xBB\xBFPedido;Situação;Origem;Comprador;E-mail;Ingressos")
        ->and($lines[1])->toContain('#1;Pago;Site;')
        ->and($lines[1])->toContain('"\'=HYPERLINK(""http://mal.example"")"')
        ->and($lines[1])->toContain(';2;300,00;0,00;0,00;300,00;0,00;;"10/06/2027 09:00";"10/06/2027 09:00"');

    $all = explode("\n", mb_trim($this->get("{$this->api}/export?q=caio")->streamedContent()));
    expect($all)->toHaveCount(2)->and($all[1])->toContain('Caio Reis');
});

it('outra edição não enxerga pedido nem histórico', function (): void {
    $order = awaitingOrder($this);
    $other = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($this->organizer);

    $this->getJson("/api/v1/editions/{$other->id}/ticketing/orders/{$order->id}/history")->assertNotFound();
    $this->postJson("/api/v1/editions/{$other->id}/ticketing/orders/{$order->id}/mark-paid", ['justification' => 'Tentando pela outra edição'])->assertNotFound();

    $csv = explode("\n", mb_trim($this->get("/api/v1/editions/{$other->id}/ticketing/orders/export")->streamedContent()));
    expect($csv)->toHaveCount(1);
});
