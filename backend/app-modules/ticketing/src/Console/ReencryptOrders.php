<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Ticketing\Models\Order;

#[Signature('ticketing:reencrypt-orders')]
#[Description('Regrava CPF e token dos pedidos com a APP_KEY atual (rotação de chave)')]
class ReencryptOrders extends Command
{
    public function handle(): int
    {
        $count = 0;

        Order::query()->withoutGlobalScopes()->lazyById()->each(function (Order $order) use (&$count): void {
            $order->forceFill(['buyer_document' => $order->buyer_document, 'access_token' => $order->access_token])->saveQuietly();
            $count++;
        });

        $this->info(sprintf('%d pedidos regravados.', $count));

        return self::SUCCESS;
    }
}
