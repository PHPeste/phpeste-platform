<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Overview;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use PHPeste\Community\Contracts\EditionOverviewContributor;
use PHPeste\Community\Data\OverviewContribution;
use PHPeste\Community\Enums\EditionPermission;
use PHPeste\Ticketing\Enums\OrderStatus;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Orders\Split;

class TicketingOverviewContributor implements EditionOverviewContributor
{
    public const int WEEKS = 8;

    public function contribute(string $editionId): OverviewContribution
    {
        $offered = (int) TicketBatch::query()->forEdition($editionId)->sum('quantity');

        if ($offered === 0) {
            return new OverviewContribution;
        }

        $settings = TicketingSetting::query()->forEdition($editionId)->first();
        $paid = OrderItem::query()->forEdition($editionId)
            ->whereHas('order', fn (Builder $query) => $query->where('status', OrderStatus::Paid->value))
            ->with('order:id,paid_at')
            ->get(['id', 'order_id']);
        $gross = (int) Order::query()->forEdition($editionId)->where('status', OrderStatus::Paid->value)->sum('total_cents');
        $awaiting = Order::query()->forEdition($editionId)->whereIn('status', [OrderStatus::Created->value, OrderStatus::AwaitingPayment->value])->count();

        $weekStart = now()->startOfWeek()->subWeeks(self::WEEKS - 1);
        $weeks = array_fill(0, self::WEEKS, 0);

        foreach ($paid as $item) {
            $paidAt = $item->order->paid_at;

            if ($paidAt instanceof Carbon && $paidAt->gte($weekStart)) {
                $index = min(self::WEEKS - 1, (int) $weekStart->diffInWeeks($paidAt));
                $weeks[$index]++;
            }
        }

        $pending = $awaiting === 0 ? [] : [[
            'key' => 'orders_awaiting_payment',
            'title' => sprintf('%d %s aguardando pagamento', $awaiting, $awaiting === 1 ? 'pedido' : 'pedidos'),
            'tone' => 'info',
            'tag' => 'Vendas',
            'action_label' => 'Ver pedidos',
            'action_path' => '/pedidos?status=awaiting_payment',
            'priority' => 3,
            'permission' => EditionPermission::ViewOrders,
        ]];

        return new OverviewContribution(
            indicators: [
                'tickets' => ['sold' => $paid->count(), 'available' => $settings?->capacity === null ? $offered : min($offered, $settings->capacity)],
                'revenue' => ['gross' => $gross, 'net' => $gross - Split::fee($gross, $settings->gateway_fee_basis_points ?? 0)],
            ],
            pending: $pending,
            sections: ['tickets_per_week' => $weeks],
        );
    }
}
