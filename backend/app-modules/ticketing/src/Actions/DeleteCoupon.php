<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Validation\ValidationException;
use PHPeste\Ticketing\Models\Coupon;

class DeleteCoupon
{
    public function handle(Coupon $coupon): void
    {
        if ($coupon->redemptions()->exists()) {
            throw ValidationException::withMessages(['coupon' => 'Este cupom já foi usado. Pause o cupom em vez de remover, para manter o histórico.']);
        }

        $coupon->delete();
    }
}
