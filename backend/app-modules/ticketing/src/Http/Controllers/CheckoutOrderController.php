<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Contracts\PaymentProcessor;
use PHPeste\Payments\Data\PaymentAttempt;
use PHPeste\Payments\Enums\PaymentMethod;
use PHPeste\Ticketing\Actions\PlaceOrder;
use PHPeste\Ticketing\Actions\StartOrderPayment;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Http\Requests\PlaceOrderRequest;
use PHPeste\Ticketing\Http\Requests\StartOrderPaymentRequest;
use PHPeste\Ticketing\Http\Resources\OrderResource;
use PHPeste\Ticketing\Models\Order;

class CheckoutOrderController
{
    public function __construct(private readonly CurrentEdition $currentEdition) {}

    public function store(PlaceOrderRequest $request, PlaceOrder $place): JsonResponse
    {
        $key = $request->header('Idempotency-Key');

        if (!is_string($key) || $key === '' || mb_strlen($key) > 100) {
            return response()->json([
                'code' => 'idempotency_key_required',
                'message' => 'O header Idempotency-Key é obrigatório para esta operação.',
                'errors' => null,
                'request_id' => $request->attributes->get('request_id'),
            ], 400);
        }

        $userId = $request->user('sanctum')?->getAuthIdentifier();
        $result = $place->handle((string) $this->currentEdition->id(), $request->order(is_string($userId) ? $userId : null), $key);

        $resource = new OrderResource($result['order']);
        $resource->withToken = true;

        return $resource->response()->setStatusCode($result['created'] ? 201 : 200);
    }

    public function show(Request $request): OrderResource
    {
        return new OrderResource($this->order($request));
    }

    public function cancel(Request $request, TransitionOrder $transition): OrderResource
    {
        $order = $this->order($request);

        return new OrderResource($transition->handle($order, OrderStatus::Cancelled, null, 'buyer')->load('items'));
    }

    public function startPayment(StartOrderPaymentRequest $request, StartOrderPayment $start): JsonResponse
    {
        $order = $this->order($request);
        $token = $request->validated('card_token');

        $attempt = $start->handle(
            $order,
            PaymentMethod::from((string) $request->validated('method')),
            $request->integer('installments', 1),
            is_string($token) && $token !== '' ? $token : null,
            $request->ip(),
            $request->filled('card_brand') ? (string) $request->string('card_brand') : null,
            $request->filled('card_issuer') ? (string) $request->string('card_issuer') : null,
        );

        return response()->json(['data' => $this->payment($attempt, $order->refresh())]);
    }

    public function showPayment(Request $request, PaymentProcessor $payments): JsonResponse
    {
        $order = $this->order($request);
        $attempt = $payments->current($order->edition_id, $order->id, refresh: in_array($order->status, [OrderStatus::Created, OrderStatus::AwaitingPayment], true));

        return response()->json(['data' => $attempt instanceof PaymentAttempt ? $this->payment($attempt, $order->refresh()) : null]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payment(PaymentAttempt $attempt, Order $order): array
    {
        return [
            'id' => $attempt->id,
            'method' => $attempt->method->value,
            'status' => $attempt->status->value,
            'status_label' => $attempt->status->label(),
            'amount' => ['amount' => $attempt->amountCents, 'currency' => 'BRL'],
            'installments' => $attempt->installments,
            'pix' => $attempt->pixCopyPaste === null ? null : ['copy_paste' => $attempt->pixCopyPaste, 'qr_code_base64' => $attempt->pixQrCode],
            'checkout_url' => $attempt->checkoutUrl,
            'expires_at' => $attempt->expiresAt?->clone()->utc()->toIso8601ZuluString(),
            'paid_at' => $attempt->paidAt?->clone()->utc()->toIso8601ZuluString(),
            'failure_reason' => $attempt->failureReason,
            'order_status' => $order->status->value,
        ];
    }

    private function order(Request $request): Order
    {
        $id = $request->route('order');
        $token = $request->header('X-Order-Token');
        abort_unless(is_string($id) && is_string($token) && $token !== '', 404);

        $order = Order::query()->with('items')->whereKey($id)->first();
        abort_unless($order instanceof Order && hash_equals($order->access_token, $token), 404);

        return $order;
    }
}
