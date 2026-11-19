<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Enums\WebhookEventStatus;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\WebhookEventRecord;

it('o mesmo evento entregue várias vezes ao mesmo tempo entra uma vez só na caixa', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'webhooks').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $account = PaymentAccount::create(['edition_id' => $edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 'segredo-concorrente'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix'], 'max_installments' => 1, 'webhook_token' => str_repeat('d', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $body = '{"id":"evt_concorrente","type":"ping"}';

    $script = __DIR__.'/../Fixtures/receive-webhook.php';
    $startAt = (string) (microtime(true) + 3);
    $results = Process::pool(function ($pool) use ($script, $account, $body, $file, $startAt): void {
        foreach (range(1, 8) as $index) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync'])->command([PHP_BINARY, $script, $account->id, $body, 'segredo-concorrente', $startAt]);
        }
    })->start()->wait();

    $outputs = collect($results)->map(fn ($result): string => mb_trim($result->output()))->values();

    expect($outputs->filter(fn (string $output): bool => $output === 'created')->count())->toBe(1)
        ->and($outputs->filter(fn (string $output): bool => $output === 'duplicate')->count())->toBe(7)
        ->and(WebhookEventRecord::query()->forEdition($edition->id)->count())->toBe(1)
        ->and(WebhookEventRecord::query()->forEdition($edition->id)->sole()->status)->toBe(WebhookEventStatus::Ignored);

    File::delete($file);
});
