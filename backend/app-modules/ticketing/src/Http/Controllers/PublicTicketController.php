<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Tenancy\CurrentEdition;
use PHPeste\Ticketing\Enums\BatchStatus;
use PHPeste\Ticketing\Enums\TicketVisibility;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

class PublicTicketController
{
    public function __invoke(Request $request, string $edition, EditionDirectory $editions, CurrentEdition $currentEdition): JsonResponse
    {
        abort_unless($editions->isPublished($edition), 404);

        /** @var JsonResponse */
        return $currentEdition->run($edition, fn (): JsonResponse => $this->list($request, $edition));
    }

    private function list(Request $request, string $edition): JsonResponse
    {

        $code = $request->query('code');
        $code = is_string($code) && $code !== '' ? Str::upper($code) : null;

        $settings = TicketingSetting::query()->forEdition($edition)->first();
        $soldOut = $settings?->capacity !== null && $settings->allocated >= $settings->capacity;

        $types = TicketType::query()->forEdition($edition)->with('batches')->orderBy('position')->orderBy('name')->get()
            ->filter(fn (TicketType $type): bool => $type->visibility === TicketVisibility::Public
                || ($type->visibility === TicketVisibility::Code && $code !== null && hash_equals((string) $type->access_code, $code)));

        return response()->json(['data' => [
            'sold_out' => $soldOut,
            'unlocked' => $code !== null && $types->contains(fn (TicketType $type): bool => $type->visibility === TicketVisibility::Code),
            'ticket_types' => array_values($types->map(fn (TicketType $type): array => $this->present($type, $soldOut))->all()),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(TicketType $type, bool $soldOut): array
    {
        $current = $type->currentBatch();
        $next = $type->batches->first(fn (TicketBatch $batch): bool => $batch->status() === BatchStatus::Scheduled);
        $status = $soldOut ? BatchStatus::SoldOut : ($current?->status() ?? $next?->status() ?? ($type->batches->contains(fn (TicketBatch $batch): bool => $batch->status() === BatchStatus::SoldOut) ? BatchStatus::SoldOut : BatchStatus::Ended));

        return [
            'id' => $type->id,
            'name' => $type->name,
            'description' => $type->description,
            'min_per_order' => $type->min_per_order,
            'max_per_order' => $type->max_per_order,
            'questions' => $type->questions ?? [],
            'status' => $status->value,
            'status_label' => $status->label(),
            'batch' => !$current instanceof TicketBatch || $soldOut ? null : [
                'id' => $current->id,
                'name' => $current->name,
                'price' => ['amount' => $current->price_cents, 'currency' => 'BRL'],
                'ends_at' => $current->ends_at?->clone()->utc()->toIso8601ZuluString(),
                'remaining' => $type->show_remaining ? $current->remaining() : null,
            ],
            'next_batch' => $current instanceof TicketBatch || $next === null ? null : [
                'name' => $next->name,
                'price' => ['amount' => $next->price_cents, 'currency' => 'BRL'],
                'opens_at' => $next->opensAt()?->clone()->utc()->toIso8601ZuluString(),
            ],
        ];
    }
}
