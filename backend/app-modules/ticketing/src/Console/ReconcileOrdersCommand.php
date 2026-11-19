<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Reconciliation\ReconcileOrders;

#[Signature('ticketing:reconcile {--edition= : Só esta edição}')]
#[Description('Compara os pedidos pagos com as cobranças do gateway e lista as divergências para a organização')]
class ReconcileOrdersCommand extends Command
{
    public const int ACTIVE_DAYS = 180;

    public function handle(ReconcileOrders $reconcile): int
    {
        $edition = $this->option('edition');
        $editions = is_string($edition) && $edition !== ''
            ? [$edition]
            : Order::query()->withoutGlobalScopes()
                ->whereNotNull('payment_account_id')
                ->where('created_at', '>=', now()->subDays(self::ACTIVE_DAYS))
                ->distinct()
                ->pluck('edition_id')
                ->all();

        foreach ($editions as $editionId) {
            $result = $reconcile->handle((string) $editionId);
            $this->info(sprintf('%s: %d pedidos, %d divergências abertas (%d novas, %d resolvidas).', $editionId, $result['orders'], $result['open'], $result['new'], $result['resolved']));
        }

        return self::SUCCESS;
    }
}
