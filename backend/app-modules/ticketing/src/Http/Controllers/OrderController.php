<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Ticketing\Actions\TransitionOrder;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Http\Resources\OrderResource;
use PHPeste\Ticketing\Models\Order;

class OrderController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        $status = OrderStatus::tryFrom((string) $request->query('status'));
        $search = mb_trim((string) $request->query('q'));

        $orders = Order::query()->with('items')
            ->when($status !== null, fn ($query) => $query->where('status', $status?->value))
            ->when($search !== '', fn ($query) => $query->where(fn (Builder $inner) => $inner
                ->where('buyer_name', 'like', "%{$search}%")
                ->orWhere('buyer_email', 'like', '%'.mb_strtolower($search).'%')
                ->when(ctype_digit(mb_ltrim($search, '#')), fn ($number) => $number->orWhere('number', (int) mb_ltrim($search, '#')))))
            ->orderByDesc('number')
            ->paginate(25);

        return OrderResource::collection($orders);
    }

    public function show(Request $request): OrderResource
    {
        Gate::authorize(EditionPermission::ViewOrders->value);

        return new OrderResource($this->order($request));
    }

    public function cancel(Request $request, TransitionOrder $transition): OrderResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $actor = $request->user()?->getAuthIdentifier();

        return new OrderResource($transition->handle($this->order($request), OrderStatus::Cancelled, is_string($actor) ? $actor : null, 'organizer')->load('items'));
    }

    private function order(Request $request): Order
    {
        $id = $request->route('order');
        abort_unless(is_string($id), 404);

        return Order::query()->with('items')->whereKey($id)->firstOrFail();
    }
}
