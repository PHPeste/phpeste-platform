<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Ticketing\Inventory\BatchRotation;

#[Description('Encerra lotes esgotados ou vencidos e abre o próximo lote quando configurado')]
#[Signature('ticketing:rotate-batches')]
class RotateTicketBatches extends Command
{
    public function handle(BatchRotation $rotation): int
    {
        $closed = $rotation->run();
        $this->info(sprintf('%d lotes encerrados.', $closed));

        return self::SUCCESS;
    }
}
