<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Notifications\Actions\QueueEmail;
use PHPeste\Notifications\Contracts\EditionMailer;
use PHPeste\Notifications\Enums\DeliveryStatus;
use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Jobs\SendEmailDelivery;
use PHPeste\Notifications\Mail\EditionMail;
use PHPeste\Notifications\Mail\EmailComposer;
use PHPeste\Notifications\Models\EmailDelivery;
use PHPeste\Notifications\Models\EmailUnsubscribe;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027', 'starts_on' => '2027-10-08', 'ends_on' => '2027-10-09']);
});

it('envia pelo contrato fora de requisição, com a identidade da edição, e registra o envio', function (): void {
    Mail::fake();

    $deliveryId = resolve(EditionMailer::class)->send(
        $this->edition->id,
        EmailTemplate::OrderConfirmed,
        'Ada@Example.com',
        'Ada Lovelace',
        ['nome' => 'Ada', 'pedido' => '#PH-1', 'cupom' => 'ignorado'],
        'https://2027.phpeste.dev/ingressos/1',
    );

    Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('ada@example.com')
        && $mail->email->subject === 'Seu ingresso para o PHPeste 2027'
        && $mail->email->fromName === 'PHPeste 2027'
        && str_contains($mail->email->html, 'https://2027.phpeste.dev/ingressos/1')
        && $mail->email->unsubscribeUrl === null);

    $delivery = EmailDelivery::query()->forEdition($this->edition->id)->findOrFail($deliveryId);

    expect($delivery->status)->toBe(DeliveryStatus::Sent)
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->sent_at)->not->toBeNull()
        ->and($delivery->variables)->toBe(['nome' => 'Ada', 'pedido' => '#PH-1']);
});

it('põe link e header de descadastro só nos e-mails não transacionais', function (): void {
    Mail::fake();

    resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::EventReminder, 'ada@example.com', 'Ada', ['nome' => 'Ada']);

    Mail::assertSent(EditionMail::class, function (EditionMail $mail): bool {
        $headers = $mail->headers()->text;

        return $mail->email->unsubscribeUrl !== null
            && str_contains($mail->email->html, 'Não quero mais receber estes avisos')
            && $headers['List-Unsubscribe'] === "<{$mail->email->unsubscribeUrl}>"
            && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

it('não envia aviso não transacional para quem se descadastrou, mas envia o transacional', function (): void {
    Mail::fake();
    EmailUnsubscribe::create(['edition_id' => $this->edition->id, 'email' => 'ada@example.com']);
    $mailer = resolve(EditionMailer::class);

    $reminder = $mailer->send($this->edition->id, EmailTemplate::EventReminder, 'ADA@example.com', 'Ada');
    $order = $mailer->send($this->edition->id, EmailTemplate::OrderConfirmed, 'ada@example.com', 'Ada');

    expect(EmailDelivery::query()->forEdition($this->edition->id)->find($reminder)?->status)->toBe(DeliveryStatus::Suppressed)
        ->and(EmailDelivery::query()->forEdition($this->edition->id)->find($order)?->status)->toBe(DeliveryStatus::Sent);

    Mail::assertSentCount(1);
});

it('registra a falha, repete com backoff e marca como falho no fim', function (): void {
    Queue::fake();
    $delivery = resolve(QueueEmail::class)->queue($this->edition->id, EmailTemplate::OrderConfirmed, 'ada@example.com', 'Ada');
    $job = new SendEmailDelivery($this->edition->id, $delivery->id);

    Queue::assertPushed(SendEmailDelivery::class, fn (SendEmailDelivery $pushed): bool => $pushed->deliveryId === $delivery->id && $pushed->editionId === $this->edition->id);
    expect($job->tries)->toBe(5)->and($job->backoff())->toBe([30, 120, 600, 1_800]);

    Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('Resend fora do ar'));

    foreach ([1, 2] as $attempt) {
        expect(fn () => $job->handle(resolve(EmailComposer::class)))->toThrow(RuntimeException::class, 'Resend fora do ar');
        $delivery->refresh();
        expect($delivery->attempts)->toBe($attempt)
            ->and($delivery->status)->toBe(DeliveryStatus::Queued)
            ->and($delivery->last_error)->toBe('Resend fora do ar');
    }

    $job->failed(new RuntimeException('Resend fora do ar'));

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed);
});

describe('pelo painel', function (): void {
    beforeEach(function (): void {
        $this->organizer = User::factory()->create(['name' => 'Rita Organizadora', 'email' => 'rita@example.com']);
        EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
        $this->base = "/api/v1/editions/{$this->edition->id}/emails";
        Sanctum::actingAs($this->organizer);
    });

    it('envia teste para quem pediu, com o rascunho do modelo', function (): void {
        Mail::fake();

        $this->postJson("{$this->base}/templates/order_confirmed/test", ['subject' => 'Teste para {nome}'])
            ->assertStatus(202)
            ->assertJsonPath('data.to_email', 'rita@example.com')
            ->assertJsonPath('data.is_test', true)
            ->assertJsonPath('data.status', 'sent');

        Mail::assertSent(EditionMail::class, fn (EditionMail $mail): bool => $mail->hasTo('rita@example.com') && $mail->email->subject === 'Teste para Rita Organizadora');
    });

    it('lista os envios e filtra por status', function (): void {
        Mail::fake();
        $mailer = resolve(EditionMailer::class);
        $mailer->send($this->edition->id, EmailTemplate::OrderConfirmed, 'a@example.com', 'A');
        $mailer->send($this->edition->id, EmailTemplate::OrderConfirmed, 'b@example.com', 'B');
        EmailDelivery::query()->forEdition($this->edition->id)->where('to_email', 'b@example.com')->update(['status' => DeliveryStatus::Failed]);
        $mailer->send(Edition::factory()->create()->id, EmailTemplate::OrderConfirmed, 'outra@example.com', 'Outra');

        $this->getJson("{$this->base}/deliveries")->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->getJson("{$this->base}/deliveries?status=failed")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.to_email', 'b@example.com');
        $this->getJson("{$this->base}/templates")->assertJsonPath('data.0.last_sent_at', fn (?string $date): bool => $date !== null);
    });

    it('reenvia um envio que falhou', function (): void {
        Mail::fake();
        $id = resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::OrderConfirmed, 'a@example.com', 'A');
        EmailDelivery::query()->forEdition($this->edition->id)->whereKey($id)->update(['status' => DeliveryStatus::Failed, 'last_error' => 'boom']);

        $this->postJson("{$this->base}/deliveries/{$id}/resend")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.attempts', 2)
            ->assertJsonPath('data.last_error', null);

        Mail::assertSentCount(2);
    });

    it('não reenvia o que ainda está na fila nem envio de outra edição', function (): void {
        Queue::fake();
        $queued = resolve(EditionMailer::class)->send($this->edition->id, EmailTemplate::OrderConfirmed, 'a@example.com', 'A');
        $other = resolve(EditionMailer::class)->send(Edition::factory()->create()->id, EmailTemplate::OrderConfirmed, 'b@example.com', 'B');

        $this->postJson("{$this->base}/deliveries/{$queued}/resend")->assertStatus(422)->assertJsonValidationErrors(['delivery']);
        $this->postJson("{$this->base}/deliveries/{$other}/resend")->assertNotFound();
    });
});
