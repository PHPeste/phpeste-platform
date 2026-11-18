<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketingSetting;
use PHPeste\Ticketing\Models\TicketType;

it('não vende além do lote nem da capacidade com compras simultâneas em processos separados', function (int $quantity, ?int $capacity, int $expected): void {
    $file = tempnam(sys_get_temp_dir(), 'tickets').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    TicketingSetting::create(['edition_id' => $edition->id, 'capacity' => $capacity]);
    $type = TicketType::create(['edition_id' => $edition->id, 'name' => 'Geral', 'max_per_order' => 5]);
    $batch = TicketBatch::create(['edition_id' => $edition->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => $quantity, 'on_sell_out' => 'close']);

    $script = __DIR__.'/../Fixtures/allocate.php';
    $results = Process::pool(function ($pool) use ($script, $edition, $batch, $file): void {
        foreach (range(1, 12) as $index) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing'])->command([PHP_BINARY, $script, $edition->id, $batch->id, '1']);
        }
    })->start()->wait();

    $outputs = collect($results)->map(fn ($result): string => mb_trim($result->output()));

    expect($outputs->filter(fn (string $output): bool => $output === 'ok')->count())->toBe($expected)
        ->and($outputs->filter(fn (string $output): bool => $output === 'sold_out')->count())->toBe(12 - $expected)
        ->and($batch->fresh()->allocated)->toBe($expected)
        ->and(TicketingSetting::query()->forEdition($edition->id)->value('allocated'))->toBe($expected);

    File::delete($file);
})->with([
    'estoque do lote' => [5, null, 5],
    'capacidade da edição' => [50, 7, 7],
]);
