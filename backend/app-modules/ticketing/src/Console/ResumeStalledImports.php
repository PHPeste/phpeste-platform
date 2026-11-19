<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\ResumeTicketImport;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Models\TicketImport;

/**
 * Importação "importando" sem avançar há mais de 10 minutos perdeu o job
 * (worker reiniciado, fila limpa). Volta para a fila do ponto onde parou.
 */
#[Signature('ticketing:resume-imports')]
#[Description('Retoma importações de compradores paradas no meio')]
class ResumeStalledImports extends Command
{
    public function handle(CurrentEdition $currentEdition, ResumeTicketImport $resume): int
    {
        $stalled = TicketImport::query()->withoutGlobalScopes()
            ->where('status', ImportStatus::Processing->value)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get(['id', 'edition_id']);

        foreach ($stalled as $import) {
            $currentEdition->run($import->edition_id, function () use ($import, $resume): void {
                $fresh = TicketImport::query()->find($import->id);

                if ($fresh instanceof TicketImport) {
                    $resume->handle($fresh);
                }
            });
        }

        $this->info(sprintf('%d importação(ões) retomada(s).', $stalled->count()));

        return self::SUCCESS;
    }
}
