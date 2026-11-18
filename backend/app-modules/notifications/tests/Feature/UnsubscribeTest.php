<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use PHPeste\Community\Models\Edition;
use PHPeste\Notifications\Models\EmailUnsubscribe;

beforeEach(function (): void {
    $this->edition = Edition::factory()->create();
    $this->url = URL::signedRoute('notifications.unsubscribe', ['edition' => $this->edition->id, 'email' => 'Ada@Example.com']);
});

it('descadastra pelo link assinado e mostra a confirmação', function (): void {
    $this->get($this->url)
        ->assertOk()
        ->assertSee('Pronto, você não vai mais receber estes avisos.');

    expect(EmailUnsubscribe::query()->forEdition($this->edition->id)->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('aceita o descadastro em um clique por POST e não duplica', function (): void {
    $this->post($this->url, ['List-Unsubscribe' => 'One-Click'])->assertOk()->assertJson(['unsubscribed' => true]);
    $this->post($this->url)->assertOk();

    expect(EmailUnsubscribe::query()->forEdition($this->edition->id)->count())->toBe(1);
});

it('recusa link sem assinatura ou com e-mail trocado', function (): void {
    $this->get("/api/v1/public/editions/{$this->edition->id}/unsubscribe?email=ada@example.com")->assertForbidden();
    $this->get(str_replace('Ada%40Example.com', 'outra%40example.com', $this->url))->assertForbidden();

    expect(EmailUnsubscribe::query()->forEdition($this->edition->id)->count())->toBe(0);
});
