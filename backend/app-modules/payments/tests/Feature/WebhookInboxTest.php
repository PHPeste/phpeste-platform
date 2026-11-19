<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Payments\Drivers\FakeGateway;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Jobs\ProcessWebhookEvent;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\WebhookEventRecord;

const INBOX_SECRET = 'segredo-da-caixa';

function inboxAccount(Edition $edition, string $token = 'b'): PaymentAccount
{
    return PaymentAccount::create(['edition_id' => $edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => INBOX_SECRET], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix'], 'max_installments' => 1, 'webhook_token' => str_repeat($token, 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
}

function deliver(object $test, string $body, string $secret = INBOX_SECRET): TestResponse
{
    return $test->call('POST', '/api/v1/webhooks/payments/'.str_repeat('b', 48), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => FakeGateway::sign($body, $secret)], $body);
}

beforeEach(function (): void {
    FakeGateway::reset();
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $this->account = inboxAccount($this->edition);
});

it('responde 2xx logo, guarda o corpo cifrado e deixa o processamento para a fila', function (): void {
    Queue::fake();
    $body = '{"id":"evt_1","type":"payment.paid","charge_id":"pix_123","buyer":"ana@exemplo.com"}';

    deliver($this, $body)->assertOk()->assertJsonPath('data', ['received' => true, 'event_id' => 'evt_1', 'duplicate' => false]);
    deliver($this, $body)->assertOk()->assertJsonPath('data.duplicate', true);

    $event = WebhookEventRecord::query()->forEdition($this->edition->id)->sole();
    expect($event)->status->toBe(WebhookEventStatus::Received)->type->toBe('payment.paid')->gateway_charge_id->toBe('pix_123')->payload->toBe($body)
        ->and(DB::table('webhook_events')->value('payload'))->not->toContain('ana@exemplo.com');
    Queue::assertPushed(ProcessWebhookEvent::class, 1);
});

it('assinatura inválida devolve 401, fica registrada na conta e não entra na caixa', function (): void {
    Log::spy();
    $this->travelTo('2027-06-10 12:00:00');

    deliver($this, '{"id":"evt_falso","type":"payment.paid"}', 'outro-segredo')->assertStatus(401)->assertJsonPath('code', 'invalid_signature');
    deliver($this, '{"id":"evt_falso","type":"payment.paid"}', 'outro-segredo')->assertStatus(401);

    expect($this->account->refresh())->webhook_rejections->toBe(2)->webhook_rejected_at->toIso8601ZuluString()->toBe('2027-06-10T12:00:00Z')
        ->and(WebhookEventRecord::query()->forEdition($this->edition->id)->count())->toBe(0);
    Log::shouldHaveReceived('warning')->with('payments.webhook_invalid_signature', Mockery::on(fn (array $context): bool => $context['account_id'] === $this->account->id))->twice();

    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $this->getJson("/api/v1/editions/{$this->edition->id}/payments/accounts")
        ->assertJsonPath('data.active.webhook_rejections', ['count' => 2, 'last_at' => '2027-06-10T12:00:00Z']);
});

it('só quem edita o gateway vê e reprocessa os avisos, e só os da própria edição', function (): void {
    deliver($this, '{"id":"evt_1","type":"ping"}')->assertOk();
    $other = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $foreign = WebhookEventRecord::create(['edition_id' => $other->id, 'payment_account_id' => inboxAccount($other, 'c')->id, 'driver' => 'fake', 'event_id' => 'evt_x', 'type' => 'ping', 'status' => WebhookEventStatus::Ignored, 'payload' => '{}']);

    $reviewer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $reviewer->id, 'role' => EditionRole::Reviewer]);
    Sanctum::actingAs($reviewer);
    $this->getJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events")->assertForbidden();

    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $this->getJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'ignored')
        ->assertJsonPath('data.0.status_label', 'Ignorado');
    $this->postJson("/api/v1/editions/{$this->edition->id}/payments/webhook-events/{$foreign->id}/reprocess")->assertNotFound();
});

it('processamento interrompido volta para a fila em vez de ficar parado', function (): void {
    Queue::fake();
    $this->travelTo('2027-06-10 12:00:00');
    deliver($this, '{"id":"evt_1","type":"ping"}')->assertOk();
    WebhookEventRecord::query()->forEdition($this->edition->id)->update(['status' => WebhookEventStatus::Processing, 'processing_started_at' => now(), 'attempts' => 1]);

    $this->travel(9)->minutes();
    $this->artisan('payments:retry-webhooks')->expectsOutput('0 webhooks devolvidos para a fila.');

    $this->travel(1)->minutes();
    $this->artisan('payments:retry-webhooks')->expectsOutput('1 webhooks devolvidos para a fila.');

    expect(WebhookEventRecord::query()->forEdition($this->edition->id)->sole())
        ->status->toBe(WebhookEventStatus::Failed)
        ->result->toBe('Processamento interrompido antes de terminar.');
    Queue::assertPushed(ProcessWebhookEvent::class, 2);
});

it('aviso que nunca saiu da fila é reenviado', function (): void {
    Queue::fake();
    deliver($this, '{"id":"evt_1","type":"ping"}')->assertOk();

    $this->artisan('payments:retry-webhooks')->expectsOutput('0 webhooks devolvidos para a fila.');
    $this->travel(3)->minutes();
    $this->artisan('payments:retry-webhooks')->expectsOutput('1 webhooks devolvidos para a fila.');
});
