<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Payments\Enums\ChargeStatus;
use PHPeste\Payments\Enums\RefundStatus;
use PHPeste\Payments\Models\PaymentAccount;
use PHPeste\Payments\Models\PaymentCharge;
use PHPeste\Payments\Models\PaymentRefund;

it('vários pedidos de reembolso total ao mesmo tempo: só um chega ao gateway', function (): void {
    $file = tempnam(sys_get_temp_dir(), 'refunds').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $account = PaymentAccount::create(['edition_id' => $edition->id, 'driver' => 'fake', 'environment' => 'sandbox', 'credentials' => ['api_key' => 'fake_key_1234', 'webhook_secret' => 's'], 'credentials_hint' => '1234', 'holder_type' => 'individual', 'holder_name' => 'Fulana', 'holder_document_masked' => '***.***.247-**', 'methods' => ['pix'], 'max_installments' => 1, 'webhook_token' => str_repeat('r', 48), 'verified_at' => now(), 'webhook_verified_at' => now()]);
    $reference = (string) Str::uuid();
    $charge = PaymentCharge::create(['edition_id' => $edition->id, 'payment_account_id' => $account->id, 'reference' => $reference, 'driver' => 'fake', 'method' => 'pix', 'gateway_charge_id' => 'pix_concorrente', 'status' => ChargeStatus::Paid, 'amount_cents' => 15_000, 'installments' => 1, 'paid_at' => now()]);

    $script = __DIR__.'/../Fixtures/refund.php';
    $startAt = (string) (microtime(true) + 3);
    $results = Process::pool(function ($pool) use ($script, $edition, $reference, $file, $startAt): void {
        foreach (range(1, 8) as $index) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'])->command([PHP_BINARY, $script, $edition->id, $reference, 'pix_concorrente', 'fake_key_1234', '15000', $startAt]);
        }
    })->start()->wait();

    $outputs = collect($results)->map(fn ($result): string => mb_trim($result->output()))->values();

    expect($outputs->filter(fn (string $output): bool => $output === 'ok')->count())->toBe(1)
        ->and($outputs->filter(fn (string $output): bool => $output === 'refused')->count())->toBe(7)
        ->and(PaymentRefund::query()->forEdition($edition->id)->count())->toBe(1)
        ->and(PaymentRefund::query()->forEdition($edition->id)->sole()->status)->toBe(RefundStatus::Completed)
        ->and(PaymentCharge::query()->forEdition($edition->id)->findOrFail($charge->id))->refunded_cents->toBe(15_000)->status->toBe(ChargeStatus::Refunded);

    File::delete($file);
});
