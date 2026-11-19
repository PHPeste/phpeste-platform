<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Actions\ProcessImportChunk;
use PHPeste\Ticketing\Enums\ImportStatus;
use PHPeste\Ticketing\Models\TicketImport;
use Throwable;

/**
 * Um lote de linhas por execução; enquanto falta linha, o job se coloca de
 * volta na fila. Arquivo grande nunca vira um job longo, e uma queda perde
 * no máximo o lote em andamento, que é refeito do mesmo ponto.
 */
class ProcessTicketImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(
        public readonly string $editionId,
        public readonly string $importId,
    ) {
        $this->afterCommit();
    }

    public function handle(ProcessImportChunk $chunk): void
    {
        $lock = Cache::lock('ticketing:import:'.$this->importId, $this->timeout);

        if (!$lock->get()) {
            return;
        }

        try {
            $done = $chunk->handle($this->editionId, $this->importId);
        } finally {
            $lock->release();
        }

        if (!$done) {
            dispatch(new self($this->editionId, $this->importId));
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Importação de compradores interrompida.', ['edition_id' => $this->editionId, 'import_id' => $this->importId, 'error' => $exception?->getMessage()]);

        resolve(CurrentEdition::class)->run($this->editionId, function (): void {
            TicketImport::query()->whereKey($this->importId)->where('status', ImportStatus::Processing->value)
                ->update(['status' => ImportStatus::Failed->value, 'failure' => 'A importação parou no meio. Retome para continuar da linha onde parou.', 'updated_at' => now()]);
        });
    }
}
