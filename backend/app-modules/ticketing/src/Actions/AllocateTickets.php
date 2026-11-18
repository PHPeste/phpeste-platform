<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Actions;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Data\Allocation;
use PHPeste\Ticketing\Inventory\BatchRotation;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;

class AllocateTickets
{
    public function __construct(
        private readonly BatchRotation $rotation,
        private readonly CurrentEdition $currentEdition,
    ) {}

    public function handle(string $editionId, string $batchId, int $quantity): Allocation
    {
        /** @var Allocation */
        return $this->currentEdition->run($editionId, fn (): Allocation => $this->allocate($editionId, $batchId, $quantity));
    }

    private function allocate(string $editionId, string $batchId, int $quantity): Allocation
    {
        $batch = TicketBatch::query()->forEdition($editionId)->with('ticketType')->whereKey($batchId)->first();

        if (!$batch instanceof TicketBatch || !$batch->isOpen()) {
            throw ValidationException::withMessages(['batch' => 'Este lote não está à venda.']);
        }

        $type = $batch->ticketType;

        if ($quantity < $type->min_per_order || $quantity > $type->max_per_order) {
            throw ValidationException::withMessages(['quantity' => sprintf('Escolha de %d a %d ingressos de "%s".', $type->min_per_order, $type->max_per_order, $type->name)]);
        }

        TicketingSetting::query()->forEdition($editionId)->firstOrCreate(['edition_id' => $editionId]);

        DB::transaction(function () use ($editionId, $batch, $quantity): void {
            $reserved = TicketBatch::query()->forEdition($editionId)
                ->whereKey($batch->id)
                ->whereNull('closed_at')
                ->whereRaw('allocated + ? <= quantity', [$quantity])
                ->increment('allocated', $quantity);

            if ($reserved === 0) {
                throw ValidationException::withMessages(['batch' => 'Não há ingressos suficientes neste lote.']);
            }

            $withinCapacity = TicketingSetting::query()->forEdition($editionId)
                ->where(fn (Builder $query) => $query->whereNull('capacity')->orWhereRaw('allocated + ? <= capacity', [$quantity]))
                ->increment('allocated', $quantity);

            if ($withinCapacity === 0) {
                throw ValidationException::withMessages(['batch' => 'Os ingressos da edição esgotaram.']);
            }
        });

        $fresh = $batch->fresh() ?? $batch;

        if ($fresh->remaining() === 0) {
            $this->rotation->close($fresh);
        }

        return new Allocation($editionId, $type->id, $batch->id, $batch->name, $quantity, $batch->price_cents);
    }
}
