<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Ticketing\Actions\ExpireOrders;

#[Signature('ticketing:expire-orders')]
#[Description('Expira pedidos com a reserva vencida e devolve os ingressos e o cupom para a venda')]
class ExpireOrdersCommand extends Command
{
    public function handle(ExpireOrders $expire): int
    {
        $this->info(sprintf('%d pedidos expirados.', $expire->handle()));

        return self::SUCCESS;
    }
}
