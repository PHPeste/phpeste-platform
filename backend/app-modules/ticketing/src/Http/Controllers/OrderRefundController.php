<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Payments\Enums\RefundReason;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Ticketing\Actions\RefundOrder;
use PHPeste\Ticketing\Http\Requests\RefundOrderRequest;
use PHPeste\Ticketing\Http\Resources\RefundResource;
use PHPeste\Ticketing\Models\Order;

class OrderRefundController
{
    public function index(Request $request, PaymentLedger $ledger): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        $order = $this->order($request);

        return RefundResource::collection($ledger->refunds($order->edition_id, $order->id));
    }

    public function store(RefundOrderRequest $request, RefundOrder $refund): JsonResponse
    {
        Gate::authorize(EditionPermission::RefundOrders->value);

        $record = $refund->handle(
            $this->order($request),
            $request->amount(),
            RefundReason::from((string) $request->string('reason')),
            $request->filled('note') ? (string) $request->string('note') : null,
            $request->boolean('cancel_tickets'),
            $request->itemIds(),
            $this->actor($request),
        );

        return new RefundResource($record)->response()->setStatusCode($record->status === RefundStatus::Unconfirmed ? 202 : 201);
    }

    public function settle(Request $request, PaymentLedger $ledger): RefundResource
    {
        Gate::authorize(EditionPermission::RefundOrders->value);

        $request->validate(['completed' => ['required', 'boolean']]);
        $order = $this->order($request);
        $refundId = $request->route('refund');
        abort_unless(is_string($refundId), 404);
        abort_unless(collect($ledger->refunds($order->edition_id, $order->id))->contains('id', $refundId), 404);

        return new RefundResource($ledger->settleRefund($order->edition_id, $refundId, $request->boolean('completed'), $this->actor($request)));
    }

    private function order(Request $request): Order
    {
        $id = $request->route('order');
        abort_unless(is_string($id), 404);

        return Order::query()->whereKey($id)->firstOrFail();
    }

    private function actor(Request $request): ?string
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_string($id) ? $id : null;
    }
}
