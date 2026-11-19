<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPeste\Audit\Models\AuditLog;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Jobs\SendEmailDelivery;
use PHPeste\Notifications\Mail\EditionMail;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Notifications\Models\EmailSuppression;
use PHPeste\Notifications\Models\EmailWebhookEvent;
use PHPeste\Notifications\Webhooks\SvixSignature;

const RESEND_SECRET = 'whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw';

beforeEach(function (): void {
    Mail::fake();
    config()->set('services.resend.webhook_secret', RESEND_SECRET);
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->delivery = EmailDelivery::query()->forEdition($this->edition->id)->findOrFail(
        resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::OrderConfirmed, 'ana@exemplo.com', 'Ana', ['nome' => 'Ana', 'pedido' => '#1']),
    );
    $this->delivery->update(['provider_message_id' => 're_4ef9a417-02e9-4d39-ad75-9611e0fcc33c']);
});

function resendEvent(object $test, string $type, array $data = [], ?string $svixId = null, ?int $timestamp = null, ?string $secret = null): TestResponse
{
    $body = json_encode(['type' => $type, 'created_at' => '2027-06-10T12:00:00Z', 'data' => ['email_id' => 're_4ef9a417-02e9-4d39-ad75-9611e0fcc33c', 'to' => ['ana@exemplo.com'], ...$data]], JSON_THROW_ON_ERROR);
    $svixId ??= 'msg_'.bin2hex(random_bytes(6));
    $timestamp ??= now()->getTimestamp();
    $signature = new SvixSignature($secret ?? RESEND_SECRET)->sign($svixId, (string) $timestamp, $body);

    return $test->call('POST', '/api/v1/webhooks/resend', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_SVIX_ID' => $svixId,
        'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
        'HTTP_SVIX_SIGNATURE' => "v1,outra-assinatura v1,{$signature}",
    ], $body);
}

function resendDelivery(object $test): EmailDelivery
{
    return EmailDelivery::query()->forEdition($test->edition->id)->findOrFail($test->delivery->id);
}

it('marca o envio como entregue', function (): void {
    resendEvent($this, 'email.delivered')->assertOk()->assertJsonPath('data.outcome', 'processed');

    expect(resendDelivery($this)->status)->toBe(DeliveryStatus::Delivered)
        ->and(resendDelivery($this)->delivered_at)->not->toBeNull()
        ->and(EmailWebhookEvent::query()->sole()->edition_id)->toBe($this->edition->id);
});

it('rejeita assinatura inválida, segredo errado ou timestamp fora da janela, sem mudar nada', function (): void {
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent']], secret: 'whsec_'.base64_encode('outro-segredo'))->assertUnauthorized();
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent']], timestamp: now()->subMinutes(6)->getTimestamp())->assertUnauthorized();
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent']], timestamp: now()->addMinutes(6)->getTimestamp())->assertUnauthorized();
    $this->postJson('/api/v1/webhooks/resend', ['type' => 'email.bounced'])->assertUnauthorized();

    config()->set('services.resend.webhook_secret');
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent']])->assertUnauthorized();

    expect(resendDelivery($this)->status)->toBe(DeliveryStatus::Sent)
        ->and(EmailSuppression::query()->forEdition($this->edition->id)->count())->toBe(0)
        ->and(EmailWebhookEvent::query()->count())->toBe(0);
});

it('o mesmo evento recebido duas vezes não duplica efeito', function (): void {
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent', 'subType' => 'General', 'message' => 'mailbox does not exist']], svixId: 'msg_repetido')->assertOk();
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent']], svixId: 'msg_repetido')->assertOk()->assertJsonPath('data.duplicate', true);

    expect(EmailWebhookEvent::query()->count())->toBe(1)
        ->and(EmailSuppression::query()->forEdition($this->edition->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'notifications.address_suppressed')->count())->toBe(1);
});

it('bounce permanente bloqueia o endereço, inclusive para e-mail transacional, até a organização liberar', function (): void {
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Permanent', 'subType' => 'General', 'message' => 'mailbox does not exist']])->assertOk();

    $bounced = resendDelivery($this);
    expect($bounced->status)->toBe(DeliveryStatus::Bounced)
        ->and($bounced->bounced_at)->not->toBeNull()
        ->and($bounced->provider_detail)->toBe('Permanent · General · mailbox does not exist');

    $next = EmailDelivery::query()->forEdition($this->edition->id)->findOrFail(
        resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::TicketTransferred, 'ANA@exemplo.com', 'Ana', ['nome' => 'Ana']),
    );
    expect($next->status)->toBe(DeliveryStatus::Suppressed)->and($next->provider_detail)->toContain('bloqueado');

    $organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $organizer->id, 'role' => EditionRole::Organizer]);
    Sanctum::actingAs($organizer);
    $api = "/api/v1/editions/{$this->edition->id}/emails";

    $this->postJson("{$api}/deliveries/{$next->id}/resend")->assertUnprocessable()->assertJsonValidationErrors(['delivery']);

    $list = $this->getJson("{$api}/suppressions")->assertOk()->json('data');
    expect($list)->toHaveCount(1)
        ->and($list[0])->toMatchArray(['email' => 'ana@exemplo.com', 'reason' => 'bounced', 'delivery_id' => $this->delivery->id]);

    $this->getJson("{$api}/deliveries?status=bounced")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status_label', 'Voltou (bounce)');

    $this->deleteJson("{$api}/suppressions/{$list[0]['id']}")->assertNoContent();
    expect(AuditLog::query()->where('action', 'notifications.suppression_lifted')->value('actor_id'))->toBe($organizer->id);

    $this->postJson("{$api}/deliveries/{$next->id}/resend")->assertOk()->assertJsonPath('data.status', 'sent');
});

it('bounce temporário e entrega atrasada não bloqueiam o endereço', function (): void {
    resendEvent($this, 'email.bounced', ['bounce' => ['type' => 'Transient', 'subType' => 'MailboxFull', 'message' => 'mailbox full']])->assertOk();
    resendEvent($this, 'email.delivery_delayed')->assertOk();

    expect(resendDelivery($this)->status)->toBe(DeliveryStatus::Sent)
        ->and(resendDelivery($this)->provider_detail)->toBe('Entrega atrasada: o servidor de destino ainda não aceitou')
        ->and(EmailSuppression::query()->forEdition($this->edition->id)->count())->toBe(0);

    $next = resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::OrderConfirmed, 'ana@exemplo.com', 'Ana', ['nome' => 'Ana']);
    expect(EmailDelivery::query()->forEdition($this->edition->id)->findOrFail($next)->status)->toBe(DeliveryStatus::Sent);
});

it('reclamação de spam marca o envio e bloqueia o endereço', function (): void {
    resendEvent($this, 'email.delivered')->assertOk();
    resendEvent($this, 'email.complained')->assertOk();

    expect(resendDelivery($this)->status)->toBe(DeliveryStatus::Complained)
        ->and(EmailSuppression::query()->forEdition($this->edition->id)->sole()->reason->value)->toBe('complained');
});

it('a edição vem do envio, nunca do webhook; envio desconhecido é ignorado', function (): void {
    $other = Edition::factory()->create();

    resendEvent($this, 'email.bounced', ['email_id' => 're_desconhecido', 'bounce' => ['type' => 'Permanent'], 'edition_id' => $other->id])->assertOk()->assertJsonPath('data.outcome', 'ignored');
    resendEvent($this, 'email.opened')->assertOk()->assertJsonPath('data.outcome', 'ignored');

    expect(EmailSuppression::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(EmailWebhookEvent::query()->count())->toBe(2);
});

it('envio que estava na fila não sai se o endereço for bloqueado antes', function (): void {
    EmailSuppression::create(['edition_id' => $this->edition->id, 'email' => 'bia@exemplo.com', 'reason' => 'bounced']);
    $queued = EmailDelivery::create(['edition_id' => $this->edition->id, 'template' => EmailTemplate::OrderConfirmed, 'to_email' => 'bia@exemplo.com', 'to_name' => 'Bia', 'variables' => [], 'status' => DeliveryStatus::Queued]);

    dispatch_sync(new SendEmailDelivery($this->edition->id, $queued->id));

    expect($queued->fresh()->status)->toBe(DeliveryStatus::Suppressed);
    Mail::assertNotSent(EditionMail::class, fn ($mail): bool => $mail->hasTo('bia@exemplo.com'));
});
