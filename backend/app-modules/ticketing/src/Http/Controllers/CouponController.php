<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\DeleteCoupon;
use PHPeste\Ticketing\Actions\SaveCoupon;
use PHPeste\Ticketing\Http\Requests\SaveCouponRequest;
use PHPeste\Ticketing\Http\Resources\CouponResource;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\CouponRedemption;

class CouponController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('ticketing.view');

        return CouponResource::collection($this->withTotals(Coupon::query())->orderBy('code')->get());
    }

    public function store(SaveCouponRequest $request, SaveCoupon $save): JsonResponse
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        return new CouponResource($save->handle((string) $this->currentEdition->id(), null, $request->coupon()))->response()->setStatusCode(201);
    }

    public function update(SaveCouponRequest $request, SaveCoupon $save): CouponResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $coupon = $save->handle((string) $this->currentEdition->id(), $this->coupon($request), $request->coupon());

        return new CouponResource($this->withTotals(Coupon::query())->findOrFail($coupon->id));
    }

    public function destroy(Request $request, DeleteCoupon $delete): Response
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $delete->handle($this->coupon($request));

        return response()->noContent();
    }

    public function redemptions(Request $request): JsonResponse
    {
        Gate::authorize('ticketing.view');

        $coupon = $this->coupon($request);
        $redemptions = $coupon->redemptions()->latest()->limit(500)->get();

        return response()->json(['data' => $redemptions->map(fn (CouponRedemption $redemption): array => [
            'id' => $redemption->id,
            'order_reference' => $redemption->order_reference,
            'email' => $redemption->email,
            'discount' => ['amount' => $redemption->discount_cents, 'currency' => 'BRL'],
            'released' => $redemption->released_at !== null,
            'created_at' => $redemption->created_at->clone()->utc()->toIso8601ZuluString(),
        ])->all()]);
    }

    /**
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    private function withTotals(Builder $query): Builder
    {
        return $query->withSum(['redemptions as discount_total' => fn (Builder $redemptions) => $redemptions->whereNull('released_at')], 'discount_cents');
    }

    private function coupon(Request $request): Coupon
    {
        $id = $request->route('coupon');
        abort_unless(is_string($id), 404);

        return Coupon::query()->whereKey($id)->firstOrFail();
    }
}
