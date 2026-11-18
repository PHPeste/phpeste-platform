<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Inventory;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Data\Quote;
use PHPeste\Ticketing\Data\QuoteLine;
use PHPeste\Ticketing\Enums\CouponKind;
use PHPeste\Ticketing\Models\Coupon;

class CouponPricing
{
    /**
     * @param  list<array{ticket_type_id: string, quantity: int, unit_price_cents: int}>  $items
     */
    public function quote(string $editionId, array $items, ?string $code): Quote
    {
        $coupon = $code === null || mb_trim($code) === '' ? null : $this->usableCoupon($editionId, $code);

        $applicable = $coupon instanceof Coupon ? array_sum(array_map(
            fn (array $item): int => $coupon->appliesTo($item['ticket_type_id']) ? $item['unit_price_cents'] * $item['quantity'] : 0,
            $items,
        )) : 0;

        if ($coupon instanceof Coupon && $applicable === 0) {
            throw ValidationException::withMessages(['coupon' => 'Este cupom não vale para os ingressos escolhidos.']);
        }

        $remainingFixed = $coupon?->kind === CouponKind::Fixed ? min($coupon->value, $applicable) : 0;
        $lines = [];

        foreach ($items as $item) {
            $subtotal = $item['unit_price_cents'] * $item['quantity'];
            $discount = 0;

            if ($coupon instanceof Coupon && $coupon->appliesTo($item['ticket_type_id'])) {
                if ($coupon->kind === CouponKind::Percent) {
                    $discount = intdiv($subtotal * $coupon->value, 100);
                } else {
                    $discount = min($subtotal, $remainingFixed);
                    $remainingFixed -= $discount;
                }
            }

            $lines[] = new QuoteLine($item['ticket_type_id'], $item['quantity'], $item['unit_price_cents'], $discount);
        }

        return new Quote($lines, $coupon?->id, $coupon?->code);
    }

    public function usableCoupon(string $editionId, string $code): Coupon
    {
        $coupon = Coupon::query()->forEdition($editionId)->where('code', Coupon::normalize($code))->first();

        if (!$coupon instanceof Coupon || $coupon->status() === 'paused') {
            throw ValidationException::withMessages(['coupon' => 'Cupom não encontrado.']);
        }

        $error = match ($coupon->status()) {
            'exhausted' => 'Este cupom já foi usado o máximo de vezes.',
            'expired' => 'Este cupom expirou.',
            'scheduled' => 'Este cupom ainda não está valendo.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['coupon' => $error]);
        }

        return $coupon;
    }
}
