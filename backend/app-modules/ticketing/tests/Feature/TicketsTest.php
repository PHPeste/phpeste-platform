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
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Ticketing\Actions\IssueTickets;
use PHPeste\Ticketing\Contracts\TicketVerifier;
use PHPeste\Ticketing\Data\TicketCheck;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\Ticket;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    FakeGateway::reset();
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create(['name' => 'PHPeste 2027']);
    $this->type = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 10]);
    PaymentAccount::create(['edition_id' => $this->edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'max_installments' => 3, 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);

    $checkout = "/api/v1/checkout/editions/{$this->edition->id}/orders";
    $order = $this->postJson($checkout, [
        'buyer' => ['name' => 'Ana Souza', 'email' => 'ana@exemplo.com', 'document' => '529.982.247-25'],
        'items' => [['ticket_type_id' => $this->type->id, 'attendees' => [['name' => 'Ana Souza', 'email' => 'Ana@Exemplo.com'], ['name' => 'Bia Lima', 'email' => 'bia@exemplo.com']]]],
    ], ['Idempotency-Key' => 'pedido-1'])->assertCreated()->json('data');
    $headers = ['X-Order-Token' => $order['access_token']];
    $this->postJson("{$checkout}/{$order['id']}/payment", ['method' => 'pix'], $headers)->assertOk();
    FakeGateway::pay(PaymentCharge::query()->forEdition($this->edition->id)->sole()->gateway_charge_id);
    $this->travel(6)->seconds();
    $this->getJson("{$checkout}/{$order['id']}/payment", $headers)->assertJsonPath('data.order_status', 'paid');

    $this->order = Order::query()->forEdition($this->edition->id)->findOrFail($order['id']);
    $this->mine = "/api/v1/attendee/editions/{$this->edition->id}/tickets";
    $this->admin = "/api/v1/editions/{$this->edition->id}/ticketing/tickets";
    $this->ana = User::factory()->create(['email' => 'ana@exemplo.com']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
});

function ticketOf(string $editionId, string $email): Ticket
{
    return Ticket::query()->forEdition($editionId)->where('holder_email', $email)->sole();
}

function emails(string $editionId, EmailTemplate $template): array
{
    return EmailDelivery::query()->forEdition($editionId)->where('template', $template->value)->pluck('to_email')->sort()->values()->all();
}

it('pagamento confirmado emite um ingresso nominal por participante e manda cada um para o próprio e-mail', function (): void {
    $tickets = Ticket::query()->forEdition($this->edition->id)->orderBy('holder_name')->get();

    expect($tickets)->toHaveCount(2)
        ->and($tickets->pluck('holder_email')->all())->toBe(['ana@exemplo.com', 'bia@exemplo.com'])
        ->and($tickets->first()->code)->toMatch('/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/')
        ->and(emails($this->edition->id, EmailTemplate::OrderConfirmed))->toBe(['ana@exemplo.com', 'bia@exemplo.com'])
        ->and(EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::OrderConfirmed->value)->first()->action_url)->toEndWith('/ingressos');

    expect(resolve(IssueTickets::class)->handle($this->order))->toBe([])
        ->and(Ticket::query()->forEdition($this->edition->id)->count())->toBe(2);
});

it('pedido gratuito também emite o ingresso', function (): void {
    $free = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Cortesia']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $free->id, 'name' => 'Único', 'price_cents' => 0, 'quantity' => 5]);

    $this->postJson("/api/v1/checkout/editions/{$this->edition->id}/orders", [
        'buyer' => ['name' => 'Caio', 'email' => 'caio@exemplo.com'],
        'items' => [['ticket_type_id' => $free->id, 'attendees' => [['name' => 'Caio', 'email' => 'caio@exemplo.com']]]],
    ], ['Idempotency-Key' => 'cortesia-1'])->assertCreated()->assertJsonPath('data.status', 'paid');

    expect(ticketOf($this->edition->id, 'caio@exemplo.com')->status->value)->toBe('active');
});

it('o QR é assinado, não leva dados pessoais e não dá para adivinhar nem adulterar', function (): void {
    Sanctum::actingAs($this->ana);
    $qr = $this->getJson($this->mine)->assertOk()->json('data.0.qr');
    $ticket = ticketOf($this->edition->id, 'ana@exemplo.com');
    $verifier = resolve(TicketVerifier::class);

    expect($qr)->toStartWith('PHT1.')
        ->not->toContain('ana')->not->toContain('Ana')->not->toContain($ticket->code)->not->toContain('exemplo')
        ->and($verifier->check($this->edition->id, $qr))->result->toBe(TicketCheck::VALID)->holderName->toBe('Ana Souza')->ticketTypeName->toBe('Geral');

    $parts = explode('.', $qr);
    $forged = implode('.', [$parts[0], $parts[1], '2', $parts[3]]);
    $other = Edition::factory()->status(EditionStatus::SalesOpen)->create();

    expect($verifier->check($this->edition->id, $forged)->result)->toBe(TicketCheck::UNKNOWN)
        ->and($verifier->check($this->edition->id, mb_substr($qr, 0, -1).(str_ends_with($qr, 'A') ? 'B' : 'A'))->result)->toBe(TicketCheck::UNKNOWN)
        ->and($verifier->check($this->edition->id, 'PHT1.'.str_repeat('0', 32).'.1.'.str_repeat('A', 27))->result)->toBe(TicketCheck::UNKNOWN)
        ->and($verifier->check($other->id, $qr)->result)->toBe(TicketCheck::UNKNOWN);
});

it('cada participante só vê e mexe nos próprios ingressos, e só com o e-mail verificado', function (): void {
    $bia = ticketOf($this->edition->id, 'bia@exemplo.com');

    Sanctum::actingAs($this->ana);
    $this->getJson($this->mine)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.holder.email', 'ana@exemplo.com')->assertJsonPath('meta.email_verified', true);
    $this->getJson("{$this->mine}/{$bia->id}")->assertNotFound();
    $this->patchJson("{$this->mine}/{$bia->id}", ['badge_name' => 'Hacker'])->assertNotFound();
    $this->postJson("{$this->mine}/{$bia->id}/transfer", ['name' => 'Eu', 'email' => 'eu@exemplo.com'])->assertNotFound();
    expect(ticketOf($this->edition->id, 'ana@exemplo.com')->holder_user_id)->toBe($this->ana->id);

    Sanctum::actingAs(User::factory()->unverified()->create(['email' => 'bia@exemplo.com']));
    $this->getJson($this->mine)->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.email_verified', false);
    $this->getJson("{$this->mine}/{$bia->id}")->assertNotFound();

    Sanctum::actingAs(User::factory()->create(['email' => 'mallory@exemplo.com']));
    $this->getJson($this->mine)->assertJsonCount(0, 'data');
    $this->getJson($this->admin)->assertForbidden();

    $this->app['auth']->forgetGuards();
    $this->getJson($this->mine)->assertUnauthorized();
});

it('acessibilidade: quem participa informa, só a organização vê; financeiro e check-in não', function (): void {
    Sanctum::actingAs($this->ana);
    $ticket = ticketOf($this->edition->id, 'ana@exemplo.com');
    $this->patchJson("{$this->mine}/{$ticket->id}", ['badge_name' => 'Aninha', 'company' => 'PHP-PB', 'tshirt_size' => 'BL-M', 'accessibility' => 'Cadeira de rodas, preciso de rampa'])
        ->assertOk()
        ->assertJsonPath('data.holder.badge_name', 'Aninha')
        ->assertJsonPath('data.holder.accessibility', 'Cadeira de rodas, preciso de rampa');
    $this->patchJson("{$this->mine}/{$ticket->id}", ['tshirt_size' => 'XXXG'])->assertStatus(422);
    expect(DB::table('tickets')->where('id', $ticket->id)->value('accessibility'))->not->toContain('rampa');

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}?accessibility=1")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.holder.accessibility', 'Cadeira de rodas, preciso de rampa');
    $this->getJson("{$this->admin}?tshirt_size=BL-M")->assertJsonCount(1, 'data');
    $this->getJson("{$this->admin}?q={$ticket->code}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.holder.badge_name', 'Aninha');
    $this->getJson($this->admin)->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.qr');

    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);
    $this->getJson($this->admin)->assertOk()->assertJsonMissingPath('data.0.holder.accessibility')->assertJsonMissingPath('data.1.holder.accessibility');
    $this->getJson("{$this->admin}?accessibility=1")->assertJsonCount(2, 'data');
    $this->getJson("{$this->admin}/{$ticket->id}")->assertJsonMissingPath('data.holder.accessibility');
    $this->patchJson("{$this->admin}/{$ticket->id}", ['badge_name' => 'X'])->assertForbidden();

    $checkin = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $checkin->id, 'role' => EditionRole::CheckIn]);
    Sanctum::actingAs($checkin);
    $this->getJson($this->admin)->assertForbidden();
});

it('transferir troca o titular, invalida o QR antigo, limpa os dados pessoais e guarda o histórico', function (): void {
    Sanctum::actingAs($this->ana);
    $ticket = ticketOf($this->edition->id, 'ana@exemplo.com');
    $this->patchJson("{$this->mine}/{$ticket->id}", ['accessibility' => 'Intérprete de Libras', 'badge_name' => 'Aninha'])->assertOk();
    $oldQr = $this->getJson("{$this->mine}/{$ticket->id}")->json('data.qr');

    $this->postJson("{$this->mine}/{$ticket->id}/transfer", ['name' => 'Ana Souza', 'email' => 'ANA@exemplo.com'])->assertStatus(422)->assertJsonPath('errors.email.0', 'O ingresso já está com este e-mail.');
    $this->postJson("{$this->mine}/{$ticket->id}/transfer", ['name' => 'Carla Dias', 'email' => 'Carla@Exemplo.com'])
        ->assertOk()
        ->assertJsonPath('data.holder.name', 'Carla Dias')
        ->assertJsonPath('data.holder.email', 'carla@exemplo.com')
        ->assertJsonMissingPath('data.qr')
        ->assertJsonMissingPath('data.holder.accessibility');

    $verifier = resolve(TicketVerifier::class);
    expect($verifier->check($this->edition->id, $oldQr)->result)->toBe(TicketCheck::REPLACED);
    $this->getJson($this->mine)->assertJsonCount(0, 'data');

    $fresh = $ticket->refresh();
    expect($fresh)->accessibility->toBeNull()->badge_name->toBeNull()->holder_user_id->toBeNull()->qr_version->toBe(2)
        ->and(emails($this->edition->id, EmailTemplate::TicketTransferred))->toBe(['carla@exemplo.com'])
        ->and(EmailDelivery::query()->forEdition($this->edition->id)->where('template', EmailTemplate::TicketTransferred->value)->sole()->variables)->toMatchArray(['remetente' => 'Ana Souza', 'ingresso' => $ticket->code])
        ->and(AuditLog::query()->where('action', 'ticketing.ticket_transferred')->sole())->actor_id->toBe($this->ana->id);

    Sanctum::actingAs(User::factory()->create(['email' => 'carla@exemplo.com']));
    $newQr = $this->getJson($this->mine)->assertJsonCount(1, 'data')->json('data.0.qr');
    expect($newQr)->not->toBe($oldQr)->and($verifier->check($this->edition->id, $newQr)->result)->toBe(TicketCheck::VALID);

    Sanctum::actingAs($this->organizer);
    $this->getJson("{$this->admin}/{$ticket->id}")
        ->assertJsonPath('data.transfers.0.from.email', 'ana@exemplo.com')
        ->assertJsonPath('data.transfers.0.to.email', 'carla@exemplo.com')
        ->assertJsonPath('data.transfers.0.channel', 'holder');
});

it('a organização transfere, corrige dados e reenvia o ingresso', function (): void {
    Sanctum::actingAs($this->organizer);
    $ticket = ticketOf($this->edition->id, 'bia@exemplo.com');

    $this->patchJson("{$this->admin}/{$ticket->id}", ['holder_name' => 'Beatriz Lima'])->assertOk()->assertJsonPath('data.holder.name', 'Beatriz Lima');
    $this->postJson("{$this->admin}/{$ticket->id}/resend")->assertOk()->assertJsonPath('data.last_sent_at', '2027-06-10T12:00:06Z');
    expect(emails($this->edition->id, EmailTemplate::OrderConfirmed))->toBe(['ana@exemplo.com', 'bia@exemplo.com', 'bia@exemplo.com']);

    $this->postJson("{$this->admin}/{$ticket->id}/transfer", ['name' => 'Davi', 'email' => 'davi@exemplo.com'])
        ->assertOk()
        ->assertJsonPath('data.transfers.0.channel', 'organizer')
        ->assertJsonPath('data.transfers.0.transferred_by', $this->organizer->id);
});

it('reembolso cancelando o ingresso derruba o QR', function (): void {
    Sanctum::actingAs($this->ana);
    $qr = $this->getJson($this->mine)->json('data.0.qr');
    $ticket = ticketOf($this->edition->id, 'ana@exemplo.com');

    Sanctum::actingAs($this->organizer);
    $this->postJson("/api/v1/editions/{$this->edition->id}/ticketing/orders/{$this->order->id}/refunds", ['reason' => 'buyer_request', 'cancel_tickets' => true, 'item_ids' => [$ticket->order_item_id]])->assertCreated();

    expect(resolve(TicketVerifier::class)->check($this->edition->id, $qr)->result)->toBe(TicketCheck::CANCELLED)
        ->and(ticketOf($this->edition->id, 'bia@exemplo.com')->status->value)->toBe('active');

    Sanctum::actingAs($this->ana);
    $this->getJson($this->mine)->assertJsonPath('data.0.status', 'cancelled')->assertJsonMissingPath('data.0.qr');
    $this->postJson("{$this->mine}/{$ticket->id}/transfer", ['name' => 'Xavier', 'email' => 'x@exemplo.com'])->assertStatus(422)->assertJsonPath('errors.ticket.0', 'Ingresso cancelado não pode ser transferido.');
});

it('participantes: filtra quem está com dados faltando e quem recebeu por transferência, com o resumo da edição', function (): void {
    Sanctum::actingAs($this->organizer);
    $ana = ticketOf($this->edition->id, 'ana@exemplo.com');
    $bia = ticketOf($this->edition->id, 'bia@exemplo.com');
    $this->patchJson("{$this->admin}/{$ana->id}", ['badge_name' => 'Aninha', 'tshirt_size' => 'M', 'accessibility' => 'Intérprete de Libras'])->assertOk();
    $this->postJson("{$this->admin}/{$bia->id}/transfer", ['name' => 'Carla Dias', 'email' => 'carla@exemplo.com'])->assertOk();

    $this->getJson($this->admin)
        ->assertOk()
        ->assertJsonPath('meta.summary', ['participants' => 2, 'complete' => 1, 'missing' => 1, 'accessibility' => 1, 'transferred' => 1]);

    $this->getJson("{$this->admin}?missing=1")
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.holder.name', 'Carla Dias')
        ->assertJsonPath('data.0.missing', ['badge_name', 'tshirt_size'])
        ->assertJsonPath('data.0.transferred_from', 'Bia Lima')
        ->assertJsonPath('meta.summary.participants', 2);
    $this->getJson("{$this->admin}?transferred=1")->assertJsonCount(1, 'data')->assertJsonPath('data.0.holder.email', 'carla@exemplo.com');
    $this->getJson("{$this->admin}?q=aninha")->assertJsonCount(1, 'data')->assertJsonPath('data.0.missing', [])->assertJsonPath('data.0.transferred_from', null);

    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);
    $this->getJson($this->admin)->assertJsonPath('meta.summary.accessibility', null);
});

it('a edição do nome do crachá fica registrada com quem mudou, de quê para quê, sem guardar o texto da acessibilidade', function (): void {
    Sanctum::actingAs($this->organizer);
    $ticket = ticketOf($this->edition->id, 'ana@exemplo.com');

    $this->patchJson("{$this->admin}/{$ticket->id}", ['badge_name' => 'Aninha', 'accessibility' => 'Cadeira de rodas'])
        ->assertOk()
        ->assertJsonPath('data.edits.0.changes.0', ['field' => 'badge_name', 'label' => 'Nome no crachá', 'from' => null, 'to' => 'Aninha'])
        ->assertJsonPath('data.edits.0.changes.1', ['field' => 'accessibility', 'label' => 'Acessibilidade', 'from' => null, 'to' => null])
        ->assertJsonPath('data.edits.0.actor.id', $this->organizer->id);
    $this->patchJson("{$this->admin}/{$ticket->id}", ['badge_name' => 'Ana S.'])->assertOk();
    $this->patchJson("{$this->admin}/{$ticket->id}", ['badge_name' => 'Ana S.'])->assertOk();

    $logs = AuditLog::query()->where('action', 'ticketing.ticket_attendee_updated')->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[1]->before)->toBe(['badge_name' => 'Aninha'])
        ->and($logs[1]->after)->toBe(['badge_name' => 'Ana S.'])
        ->and(json_encode($logs[0]->after))->not->toContain('Cadeira');

    $this->getJson("{$this->admin}/{$ticket->id}")
        ->assertJsonCount(2, 'data.edits')
        ->assertJsonPath('data.edits.1.changes.0.from', 'Aninha')
        ->assertJsonPath('data.edits.1.changes.0.to', 'Ana S.');

    Sanctum::actingAs($this->ana);
    $this->patchJson("{$this->mine}/{$ticket->id}", ['tshirt_size' => 'G'])->assertOk();
    expect(AuditLog::query()->where('action', 'ticketing.ticket_attendee_updated')->orderByDesc('id')->first()->actor_id)->toBe($this->ana->id);
});

it('cobra os dados faltando por e-mail, uma vez por pessoa, só depois de confirmar o número da prévia', function (): void {
    Sanctum::actingAs($this->organizer);
    $ana = ticketOf($this->edition->id, 'ana@exemplo.com');
    $this->patchJson("{$this->admin}/{$ana->id}", ['badge_name' => 'Aninha', 'tshirt_size' => 'M'])->assertOk();

    $preview = $this->getJson("{$this->admin}/data-reminder")->assertOk();
    $preview->assertJsonPath('data.recipients', 1)->assertJsonPath('data.email.subject', 'Complete seus dados para o crachá do PHPeste 2027');
    expect($preview->json('data.email.text'))->toContain('Oi, Bia Lima!')->toContain('/ingressos')
        ->and(emails($this->edition->id, EmailTemplate::AttendeeDataReminder))->toBe([]);

    $this->postJson("{$this->admin}/data-reminder", ['confirm' => true, 'recipients' => 2])->assertStatus(422)->assertJsonPath('errors.confirm.0', 'Confirme a cobrança por e-mail para 1 pessoa.');
    $this->postJson("{$this->admin}/data-reminder", ['confirm' => true, 'recipients' => 1])->assertStatus(202)->assertJsonPath('data.recipients', 1);

    expect(emails($this->edition->id, EmailTemplate::AttendeeDataReminder))->toBe(['bia@exemplo.com'])
        ->and(AuditLog::query()->where('action', 'ticketing.attendee_data_reminder_sent')->sole()->after)->toBe(['recipients' => 1]);

    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    Sanctum::actingAs($finance);
    $this->getJson("{$this->admin}/data-reminder")->assertForbidden();
    $this->postJson("{$this->admin}/data-reminder", ['confirm' => true, 'recipients' => 1])->assertForbidden();
});

it('exporta os crachás dos ingressos válidos, sem e-mail nem acessibilidade e sem virar fórmula', function (): void {
    Sanctum::actingAs($this->organizer);
    $ana = ticketOf($this->edition->id, 'ana@exemplo.com');
    $this->patchJson("{$this->admin}/{$ana->id}", ['badge_name' => '=Aninha', 'company' => 'PHP-PB', 'tshirt_size' => 'BL-M', 'accessibility' => 'Rampa'])->assertOk();

    $csv = $this->get("{$this->admin}/badges")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

    expect($csv)->toStartWith("\u{FEFF}Código;\"Nome no crachá\";\"Nome completo\";Empresa;Ingresso;Camiseta")
        ->toContain("{$ana->code};'=Aninha;\"Ana Souza\";PHP-PB;Geral;BL-M")
        ->toContain(';"Bia Lima";"Bia Lima";;Geral;')
        ->not->toContain('exemplo.com')
        ->not->toContain('Rampa');
});
