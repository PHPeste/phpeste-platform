<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Cfp\Actions\ProcessConfirmationDeadlines;

#[Description('Lembra palestrantes perto do prazo de confirmação e marca como sem resposta quem passou do prazo')]
#[Signature('cfp:confirmation-deadlines')]
class ProcessConfirmationDeadlinesCommand extends Command
{
    public function handle(ProcessConfirmationDeadlines $process): int
    {
        $result = $process->handle();

        $this->info("Lembretes: {$result['reminded']}. Sem resposta: {$result['expired']}.");

        return self::SUCCESS;
    }
}
