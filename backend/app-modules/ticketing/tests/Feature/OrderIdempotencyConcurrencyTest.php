<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Ticketing\Models\Order;
use PHPeste\Ticketing\Models\TicketBatch;
use PHPeste\Ticketing\Models\TicketType;

it('a mesma Idempotency-Key enviada ao mesmo tempo cria um pedido só e reserva um ingresso só', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'orders').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $type = TicketType::create(['edition_id' => $edition->id, 'name' => 'Geral']);
    $batch = TicketBatch::create(['edition_id' => $edition->id, 'ticket_type_id' => $type->id, 'name' => '1º lote', 'price_cents' => 15_000, 'quantity' => 100]);

    PaymentAccount::create(['edition_id' => $edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-webhook'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana, financeiro da PHPeste', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix', 'card'], 'webhook_token' => str_repeat('a', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $script = __DIR__.'/../Fixtures/place-order.php';
    $results = Process::pool(function ($pool) use ($script, $edition, $type, $file): void {
        foreach (range(1, 6) as $index) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing'])->command([PHP_BINARY, $script, $edition->id, $type->id, 'clique-duplo']);
        }
    })->start()->wait();

    $ids = collect($results)->map(fn ($result): string => mb_trim($result->output()))->unique()->values();

    expect($ids)->toHaveCount(1)
        ->and(Order::query()->forEdition($edition->id)->count())->toBe(1)
        ->and(Order::query()->forEdition($edition->id)->value('id'))->toBe($ids->first())
        ->and($batch->fresh()->allocated)->toBe(1);

    File::delete($file);
});
