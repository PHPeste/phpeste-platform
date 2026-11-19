<?php

declare(strict_types=1);

use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;

beforeEach(function (): void {
    $this->edition = Edition::factory()->status(EditionStatus::CfpOpen)->create(['name' => 'PHPeste 2027', 'starts_on' => '2027-10-08', 'ends_on' => '2027-10-09']);
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->url = "/api/v1/editions/{$this->edition->id}/emails/templates";
    Sanctum::actingAs($this->organizer);
});

it('lista os modelos da edição com o padrão de cada um', function (): void {
    $this->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(11, 'data')
        ->assertJsonPath('data.0.id', 'order_confirmed')
        ->assertJsonPath('data.0.label', 'Confirmação de compra')
        ->assertJsonPath('data.0.trigger', 'Ao pagar o pedido')
        ->assertJsonPath('data.0.variables', ['nome', 'edicao', 'pedido', 'data'])
        ->assertJsonPath('data.0.subject', 'Seu ingresso para o {edicao}')
        ->assertJsonPath('data.0.customized', false)
        ->assertJsonPath('data.0.last_sent_at', null)
        ->assertJsonPath('data.6.transactional', false)
        ->assertJsonPath('data.10.id', 'speaker_invited')
        ->assertJsonPath('data.10.subject', 'Convite para palestrar no {edicao}');
});

it('salva um modelo personalizado e restaura o padrão', function (): void {
    $this->putJson("{$this->url}/order_confirmed", ['subject' => 'Ingresso garantido, {nome}!', 'body' => "Oi, {nome}!\n\nPedido {pedido} pago."])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Ingresso garantido, {nome}!')
        ->assertJsonPath('data.customized', true);

    $this->getJson("{$this->url}/order_confirmed")->assertJsonPath('data.body', "Oi, {nome}!\n\nPedido {pedido} pago.");

    $this->deleteJson("{$this->url}/order_confirmed")
        ->assertOk()
        ->assertJsonPath('data.subject', 'Seu ingresso para o {edicao}')
        ->assertJsonPath('data.customized', false);
});

it('rejeita variável desconhecida ao salvar', function (): void {
    $this->putJson("{$this->url}/event_reminder", ['subject' => 'Falta pouco, {nome}', 'body' => 'Seu pedido {pedido} e {cupom}.'])
        ->assertStatus(422)
        ->assertJsonPath('errors.body.0', 'Variável desconhecida no modelo: {pedido}, {cupom}. Use só {nome}, {edicao}, {data}.');

    $this->assertDatabaseCount('email_templates', 0);
});

it('responde 404 para modelo que não existe', function (): void {
    $this->getJson("{$this->url}/newsletter")->assertNotFound();
});

it('pré-visualiza o rascunho com a identidade e os dados da edição, escapando HTML', function (): void {
    $this->postJson("{$this->url}/order_confirmed/preview", ['body' => "Oi, {nome}! <script>alert(1)</script>\n\nAté {data}."])
        ->assertOk()
        ->assertJsonPath('data.subject', 'Seu ingresso para o PHPeste 2027')
        ->assertJsonPath('data.html', fn (string $html): bool => str_contains($html, 'Oi, Ada Lovelace! &lt;script&gt;alert(1)&lt;/script&gt;')
            && !str_contains($html, '<script')
            && str_contains($html, 'Até 8 e 9 de outubro.')
            && str_contains($html, 'background:#6B2A12')
            && str_contains($html, 'Ver meu ingresso')
            && str_contains($html, 'PHPeste 2027 · 8 e 9 de outubro · João Pessoa'))
        ->assertJsonPath('data.text', fn (string $text): bool => str_contains($text, 'Oi, Ada Lovelace! <script>'));

    $this->assertDatabaseCount('email_templates', 0);
});

it('usa o tema publicado da edição no layout', function (): void {
    $this->postJson("/api/v1/editions/{$this->edition->id}/theme/draft/preset", ['preset' => 'mare']);
    $this->postJson("/api/v1/editions/{$this->edition->id}/theme/publish")->assertOk();

    $this->postJson("{$this->url}/order_confirmed/preview")
        ->assertOk()
        ->assertJsonPath('data.html', fn (string $html): bool => str_contains($html, 'background:#0F1B2D') && str_contains($html, 'background:#2C78CA'));
});

it('deixa só o organizador mexer nos modelos', function (EditionRole $role): void {
    $user = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $user->id, 'role' => $role]);
    Sanctum::actingAs($user);

    $this->getJson($this->url)->assertForbidden();
    $this->putJson("{$this->url}/order_confirmed", ['subject' => 'x', 'body' => 'y'])->assertForbidden();
    $this->postJson("{$this->url}/order_confirmed/preview")->assertForbidden();
    $this->postJson("{$this->url}/order_confirmed/test")->assertForbidden();
    $this->getJson("/api/v1/editions/{$this->edition->id}/emails/deliveries")->assertForbidden();
})->with([EditionRole::Finance, EditionRole::Reviewer, EditionRole::CheckIn, EditionRole::Volunteer]);

it('isola os modelos entre edições', function (): void {
    $this->putJson("{$this->url}/order_confirmed", ['subject' => 'Só da 2027', 'body' => 'Oi, {nome}!'])->assertOk();

    $other = Edition::factory()->create();
    EditionMember::factory()->for($other)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);

    $this->getJson("/api/v1/editions/{$other->id}/emails/templates/order_confirmed")
        ->assertOk()
        ->assertJsonPath('data.subject', 'Seu ingresso para o {edicao}')
        ->assertJsonPath('data.customized', false);
});
