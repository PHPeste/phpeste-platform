<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Support\Facades\DB;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\CouponRedemption;

class ReleaseCouponRedemption
{
    public function handle(CouponRedemption $redemption): void
    {
        DB::transaction(function () use ($redemption): void {
            $released = CouponRedemption::query()->forEdition($redemption->edition_id)
                ->whereKey($redemption->id)
                ->whereNull('released_at')
                ->update(['released_at' => now()]);

            if ($released === 1) {
                Coupon::query()->forEdition($redemption->edition_id)->whereKey($redemption->coupon_id)->where('used', '>', 0)->decrement('used');
            }
        });
    }
}
