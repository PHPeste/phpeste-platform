<?php

declare(strict_types=1);

use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use PHPeste\Community\Enums\EditionRole;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Community\Models\EditionMember;
use PHPeste\Identity\Models\User;
use PHPeste\Ticketing\Actions\RedeemCoupon;
use PHPeste\Ticketing\Actions\ReleaseCouponRedemption;
use PHPeste\Ticketing\Inventory\CouponPricing;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

beforeEach(function (): void {
    $this->travelTo('2027-06-10 12:00:00');
    $this->edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $this->organizer = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $this->organizer->id, 'role' => EditionRole::Organizer]);
    $this->base = "/api/v1/editions/{$this->edition->id}/ticketing";
    $this->general = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Geral']);
    $this->student = TicketType::create(['edition_id' => $this->edition->id, 'name' => 'Estudante']);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->general->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 100]);
    TicketBatch::create(['edition_id' => $this->edition->id, 'ticket_type_id' => $this->student->id, 'name' => 'Único', 'price_cents' => 7_500, 'quantity' => 100]);
    Sanctum::actingAs($this->organizer);
});

function createCoupon(object $test, array $overrides = []): string
{
    return $test->postJson("{$test->base}/coupons", ['code' => 'comunidade', 'kind' => 'percent', 'value' => 20, ...$overrides])->assertCreated()->json('data.id');
}

function check(object $test, string $code, array $items): TestResponse
{
    return $test->postJson("/api/v1/public/editions/{$test->edition->id}/coupons/check", ['code' => $code, 'items' => $items]);
}

it('cria cupom com o código em maiúsculas e valida tipo, valor, validade e código repetido', function (): void {
    createCoupon($this);

    $this->getJson("{$this->base}/coupons")
        ->assertOk()
        ->assertJsonPath('data.0.code', 'COMUNIDADE')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.remaining', null);

    $this->postJson("{$this->base}/coupons", ['code' => 'Comunidade', 'kind' => 'fixed', 'value' => 100])->assertStatus(422)->assertJsonValidationErrors(['code']);
    $this->postJson("{$this->base}/coupons", ['code' => 'MUITO', 'kind' => 'percent', 'value' => 120])
        ->assertStatus(422)->assertJsonPath('errors.value.0', 'O desconto percentual vai de 1 a 100.');
    $this->postJson("{$this->base}/coupons", ['code' => 'DATA', 'kind' => 'fixed', 'value' => 100, 'starts_at' => '2027-07-01', 'ends_at' => '2027-06-01'])
        ->assertStatus(422)->assertJsonPath('errors.ends_at.0', 'O fim da validade precisa ser depois do início.');
    $this->postJson("{$this->base}/coupons", ['code' => 'OUTRA', 'kind' => 'fixed', 'value' => 100, 'ticket_type_ids' => [Edition::factory()->create()->id]])
        ->assertStatus(422)->assertJsonValidationErrors(['ticket_type_ids.0']);
});

it('compara o código sem diferenciar maiúsculas e calcula percentual só nos ingressos aplicáveis', function (): void {
    createCoupon($this, ['code' => 'Estudante50', 'value' => 50, 'ticket_type_ids' => [$this->student->id]]);

    check($this, '  estudante50 ', [
        ['ticket_type_id' => $this->general->id, 'quantity' => 1],
        ['ticket_type_id' => $this->student->id, 'quantity' => 2],
    ])
        ->assertOk()
        ->assertJsonPath('data.code', 'ESTUDANTE50')
        ->assertJsonPath('data.subtotal.amount', 30_000)
        ->assertJsonPath('data.discount.amount', 7_500)
        ->assertJsonPath('data.total.amount', 22_500)
        ->assertJsonPath('data.lines.0.discount.amount', 0)
        ->assertJsonPath('data.requires_payment', true);

    check($this, 'ESTUDANTE50', [['ticket_type_id' => $this->general->id, 'quantity' => 1]])
        ->assertStatus(422)->assertJsonPath('errors.coupon.0', 'Este cupom não vale para os ingressos escolhidos.');
});

it('valor fixo nunca deixa o total negativo e cortesia de 100% não precisa de pagamento', function (): void {
    createCoupon($this, ['code' => 'PATROCINIO', 'kind' => 'fixed', 'value' => 20_000]);
    createCoupon($this, ['code' => 'CORTESIA', 'value' => 100]);

    check($this, 'patrocinio', [['ticket_type_id' => $this->general->id, 'quantity' => 1], ['ticket_type_id' => $this->student->id, 'quantity' => 1]])
        ->assertJsonPath('data.discount.amount', 20_000)
        ->assertJsonPath('data.lines.0.discount.amount', 15_000)
        ->assertJsonPath('data.lines.1.discount.amount', 5_000)
        ->assertJsonPath('data.total.amount', 2_500);
    check($this, 'patrocinio', [['ticket_type_id' => $this->student->id, 'quantity' => 1]])
        ->assertJsonPath('data.total.amount', 0)
        ->assertJsonPath('data.requires_payment', false);
    check($this, 'cortesia', [['ticket_type_id' => $this->general->id, 'quantity' => 3]])
        ->assertJsonPath('data.total.amount', 0)
        ->assertJsonPath('data.requires_payment', false);
});

it('recusa cupom inexistente, pausado, fora da validade e esgotado, e o uso liberado volta', function (): void {
    $items = [['ticket_type_id' => $this->general->id, 'quantity' => 1]];
    createCoupon($this, ['code' => 'PAUSADO', 'active' => false]);
    createCoupon($this, ['code' => 'FUTURO', 'starts_at' => '2027-07-01T00:00:00Z']);
    createCoupon($this, ['code' => 'VENCIDO', 'ends_at' => '2027-06-01T00:00:00Z']);
    $single = createCoupon($this, ['code' => 'UNICO', 'max_uses' => 1]);

    check($this, 'NAOEXISTE', $items)->assertStatus(422)->assertJsonPath('errors.coupon.0', 'Cupom não encontrado.');
    check($this, 'pausado', $items)->assertJsonPath('errors.coupon.0', 'Cupom não encontrado.');
    check($this, 'futuro', $items)->assertJsonPath('errors.coupon.0', 'Este cupom ainda não está valendo.');
    check($this, 'vencido', $items)->assertJsonPath('errors.coupon.0', 'Este cupom expirou.');

    $redemption = resolve(RedeemCoupon::class)->handle($this->edition->id, $single, 3_000, 'PED-1', ' Ana@Exemplo.com ');
    check($this, 'unico', $items)->assertJsonPath('errors.coupon.0', 'Este cupom já foi usado o máximo de vezes.');
    expect(fn () => resolve(RedeemCoupon::class)->handle($this->edition->id, $single, 3_000))->toThrow(ValidationException::class);

    resolve(ReleaseCouponRedemption::class)->handle($redemption);
    resolve(ReleaseCouponRedemption::class)->handle($redemption);

    check($this, 'unico', $items)->assertOk();
    $this->getJson("{$this->base}/coupons")->assertJsonPath('data.2.code', 'UNICO')->assertJsonPath('data.2.used', 0);
});

it('relatório de uso por cupom mostra usos, desconto total e cada uso', function (): void {
    $id = createCoupon($this, ['max_uses' => 10]);
    $redeem = resolve(RedeemCoupon::class);
    $redeem->handle($this->edition->id, $id, 3_000, 'PED-1', 'Ana@Exemplo.com');

    $released = $redeem->handle($this->edition->id, $id, 3_000, 'PED-2', 'bia@exemplo.com');
    $redeem->handle($this->edition->id, $id, 1_500, 'PED-3');
    resolve(ReleaseCouponRedemption::class)->handle($released);

    $this->getJson("{$this->base}/coupons")
        ->assertJsonPath('data.0.used', 2)
        ->assertJsonPath('data.0.remaining', 8)
        ->assertJsonPath('data.0.discount_total.amount', 4_500);

    $this->getJson("{$this->base}/coupons/{$id}/redemptions")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonFragment(['order_reference' => 'PED-1', 'email' => 'ana@exemplo.com', 'released' => false])
        ->assertJsonFragment(['order_reference' => 'PED-2', 'released' => true]);
});

it('cupom usado não muda de código, não baixa o limite abaixo dos usos e não é removido', function (): void {
    $id = createCoupon($this, ['max_uses' => 5]);
    resolve(RedeemCoupon::class)->handle($this->edition->id, $id, 3_000);
    resolve(RedeemCoupon::class)->handle($this->edition->id, $id, 3_000);

    $this->patchJson("{$this->base}/coupons/{$id}", ['code' => 'OUTRO'])->assertStatus(422)->assertJsonValidationErrors(['code']);
    $this->patchJson("{$this->base}/coupons/{$id}", ['max_uses' => 1])
        ->assertStatus(422)->assertJsonPath('errors.max_uses.0', 'Este cupom já foi usado 2 vezes; o limite não pode ser menor.');
    $this->deleteJson("{$this->base}/coupons/{$id}")->assertStatus(422);
    $this->patchJson("{$this->base}/coupons/{$id}", ['active' => false])->assertOk()->assertJsonPath('data.status', 'paused');

    $unused = createCoupon($this, ['code' => 'NOVO']);
    $this->deleteJson("{$this->base}/coupons/{$unused}")->assertNoContent();
});

it('financeiro vê os cupons mas só a organização altera, e a checagem pública exige edição publicada', function (): void {
    $finance = User::factory()->create();
    EditionMember::factory()->for($this->edition)->create(['user_id' => $finance->id, 'role' => EditionRole::Finance]);
    createCoupon($this);

    Sanctum::actingAs($finance);
    $this->getJson("{$this->base}/coupons")->assertOk();
    $this->postJson("{$this->base}/coupons", ['code' => 'X1X', 'kind' => 'fixed', 'value' => 1])->assertForbidden();

    $draft = Edition::factory()->create();
    $this->postJson("/api/v1/public/editions/{$draft->id}/coupons/check", ['code' => 'X', 'items' => [['ticket_type_id' => $this->general->id, 'quantity' => 1]]])->assertNotFound();
});

it('o cálculo usa o preço do lote atual informado e arredonda o percentual para baixo', function (): void {
    createCoupon($this, ['code' => 'TERCO', 'value' => 33]);

    $quote = resolve(CouponPricing::class)->quote($this->edition->id, [['ticket_type_id' => $this->general->id, 'quantity' => 1, 'unit_price_cents' => 9_999]], 'terco');

    expect($quote->discountCents())->toBe(3_299)->and($quote->totalCents())->toBe(6_700);
});
