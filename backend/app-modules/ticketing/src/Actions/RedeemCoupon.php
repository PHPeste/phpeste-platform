<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\CouponRedemption;

class RedeemCoupon
{
    public function handle(string $editionId, string $couponId, int $discountCents, ?string $orderReference = null, ?string $email = null): CouponRedemption
    {
        return DB::transaction(function () use ($editionId, $couponId, $discountCents, $orderReference, $email): CouponRedemption {
            $taken = Coupon::query()->forEdition($editionId)
                ->whereKey($couponId)
                ->where('active', true)
                ->where(fn (Builder $query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->where(fn (Builder $query) => $query->whereNull('max_uses')->orWhereColumn('used', '<', 'max_uses'))
                ->increment('used');

            if ($taken === 0) {
                throw ValidationException::withMessages(['coupon' => 'Este cupom não está mais disponível.']);
            }

            return CouponRedemption::create([
                'edition_id' => $editionId,
                'coupon_id' => $couponId,
                'order_reference' => $orderReference,
                'email' => $email === null ? null : mb_strtolower(mb_trim($email)),
                'discount_cents' => $discountCents,
            ]);
        });
    }
}
