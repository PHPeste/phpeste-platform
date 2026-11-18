<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Resources;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;
use PHPeste\Ticketing\Models\Coupon;

class CouponResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $coupon = $this->resource;

        if (!$coupon instanceof Coupon) {
            return [];
        }

        $discount = $coupon->getAttribute('discount_total');

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'description' => $coupon->description,
            'kind' => $coupon->kind->value,
            'kind_label' => $coupon->kind->label(),
            'value' => $coupon->value,
            'max_uses' => $coupon->max_uses,
            'used' => $coupon->used,
            'remaining' => $coupon->remaining(),
            'starts_at' => $this->isoDate($coupon->starts_at),
            'ends_at' => $this->isoDate($coupon->ends_at),
            'ticket_type_ids' => $coupon->ticket_type_ids ?? [],
            'active' => $coupon->active,
            'status' => $coupon->status(),
            'discount_total' => $this->money(is_numeric($discount) ? (int) $discount : 0),
        ];
    }
}
