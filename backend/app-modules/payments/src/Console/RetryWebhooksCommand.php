<?php

declare(strict_types=1);

namespace PHPeste\Payments\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Payments\Webhooks\RetryWebhooks;

#[Signature('payments:retry-webhooks')]
#[Description('Devolve para a fila os webhooks com falha no horário da nova tentativa e os que ficaram parados')]
class RetryWebhooksCommand extends Command
{
    public function handle(RetryWebhooks $retry): int
    {
        $this->info(sprintf('%d webhooks devolvidos para a fila.', $retry->handle()));

        return self::SUCCESS;
    }
}
