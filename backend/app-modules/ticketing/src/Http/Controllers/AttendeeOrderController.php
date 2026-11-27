<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Payments\Contracts\PaymentLedger;
use PHPeste\Ticketing\Http\Resources\AttendeeOrderResource;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Tickets\TicketOwnership;

class AttendeeOrderController
{
    public function __construct(
        private readonly CurrentEdition $currentEdition,
        private readonly TicketOwnership $ownership,
        private readonly PaymentLedger $ledger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof Authenticatable, 401);

        $editionId = (string) $this->currentEdition->id();
        $id = (string) $user->getAuthIdentifier();
        $email = $this->ownership->verifiedEmail($user);

        $orders = Order::query()->forEdition($editionId)
            ->where(fn (Builder $query) => $query
                ->where('user_id', $id)
                ->when($email !== null, fn (Builder $byEmail) => $byEmail->orWhere('buyer_email', $email)))
            ->with('items')
            ->latest()
            ->orderByDesc('number')
            ->get();

        return response()->json([
            'data' => $orders->map(function (Order $order) use ($request, $editionId): array {
                $resource = new AttendeeOrderResource($order);
                $resource->refunds = $this->ledger->refunds($editionId, $order->id);

                return $resource->resolve($request);
            })->all(),
            'meta' => ['email_verified' => $email !== null],
        ]);
    }
}
