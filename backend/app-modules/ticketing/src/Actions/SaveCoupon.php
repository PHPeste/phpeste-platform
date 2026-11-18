<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Enums\CouponKind;
use PHPeste\Ticketing\Models\Coupon;

class SaveCoupon
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(string $editionId, ?Coupon $coupon, array $attributes): Coupon
    {
        $kind = isset($attributes['kind']) ? CouponKind::from((string) $attributes['kind']) : $coupon?->kind;
        $value = (int) ($attributes['value'] ?? $coupon->value ?? 0);

        if ($kind === CouponKind::Percent && $value > 100) {
            throw ValidationException::withMessages(['value' => 'O desconto percentual vai de 1 a 100.']);
        }

        if ($coupon instanceof Coupon && array_key_exists('max_uses', $attributes) && $attributes['max_uses'] !== null && (int) $attributes['max_uses'] < $coupon->used) {
            throw ValidationException::withMessages(['max_uses' => sprintf('Este cupom já foi usado %d vezes; o limite não pode ser menor.', $coupon->used)]);
        }

        if ($coupon instanceof Coupon && isset($attributes['code']) && $attributes['code'] !== $coupon->code && $coupon->used > 0) {
            throw ValidationException::withMessages(['code' => 'Este cupom já foi usado; o código não pode mudar. Crie outro cupom.']);
        }

        if (!$coupon instanceof Coupon) {
            return Coupon::create([...$attributes, 'edition_id' => $editionId]);
        }

        $coupon->update($attributes);

        return $coupon;
    }
}
