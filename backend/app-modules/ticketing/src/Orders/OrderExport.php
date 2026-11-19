<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

use Illuminate\Database\Eloquent\Builder;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\OrderItem;

/**
 * Planilha dos pedidos para conferência: uma linha por pedido, valores em
 * reais com vírgula e datas no fuso da edição. Célula que começa com
 * =, +, - ou @ ganha um apóstrofo para a planilha não executar fórmula.
 */
class OrderExport
{
    /**
     * @param  Builder<Order>  $orders
     * @param  resource  $out
     */
    public function write(Builder $orders, string $timezone, $out): void
    {
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Pedido', 'Situação', 'Origem', 'Comprador', 'E-mail', 'Ingressos', 'Subtotal', 'Desconto', 'Taxa', 'Total', 'Reembolsado', 'Cupom', 'Criado em', 'Pago em'], ';', '"', '');

        $orders->with('items')->chunk(500, function ($chunk) use ($out, $timezone): void {
            foreach ($chunk as $order) {
                fputcsv($out, array_map($this->cell(...), [
                    sprintf('#%d', $order->number),
                    $order->status->label(),
                    $order->external() ? (string) $order->external_platform : 'Site',
                    $order->buyer_name,
                    $order->buyer_email,
                    (string) $order->items->filter(fn (OrderItem $item): bool => $item->cancelled_at === null)->count(),
                    $this->reais($order->subtotal_cents),
                    $this->reais($order->discount_cents),
                    $this->reais($order->fee_cents),
                    $this->reais($order->total_cents),
                    $this->reais($order->refunded_cents),
                    (string) $order->coupon_code,
                    $order->created_at->clone()->setTimezone($timezone)->format('d/m/Y H:i'),
                    $order->paid_at?->clone()->setTimezone($timezone)->format('d/m/Y H:i') ?? '',
                ]), ';', '"', '');
            }
        });
    }

    private function reais(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    private function cell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }
}
