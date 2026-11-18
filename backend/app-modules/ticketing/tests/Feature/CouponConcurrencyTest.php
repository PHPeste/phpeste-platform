<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPeste\Community\Enums\EditionStatus;
use PHPeste\Community\Models\Edition;
use PHPeste\Ticketing\Models\Coupon;
use PHPeste\Ticketing\Models\CouponRedemption;

it('duas pessoas usando o último uso ao mesmo tempo: só uma é aprovada', function (int $maxUses, int $buyers): void {
    $file = tempnam(sys_get_temp_dir(), 'coupons').'.sqlite';
    touch($file);
    config(['database.connections.sqlite.database' => $file]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);

    $edition = Edition::factory()->status(EditionStatus::SalesOpen)->create();
    $coupon = Coupon::create(['edition_id' => $edition->id, 'code' => 'ULTIMO', 'kind' => 'percent', 'value' => 100, 'max_uses' => $maxUses]);

    $script = __DIR__.'/../Fixtures/redeem.php';
    $results = Process::pool(function ($pool) use ($script, $edition, $coupon, $file, $buyers): void {
        foreach (range(1, $buyers) as $index) {
            $pool->as((string) $index)->env(['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $file, 'APP_ENV' => 'testing'])->command([PHP_BINARY, $script, $edition->id, $coupon->id]);
        }
    })->start()->wait();

    $approved = collect($results)->filter(fn ($result): bool => mb_trim($result->output()) === 'ok')->count();

    expect($approved)->toBe($maxUses)
        ->and(Coupon::query()->forEdition($edition->id)->value('used'))->toBe($maxUses)
        ->and(CouponRedemption::query()->forEdition($edition->id)->count())->toBe($maxUses);

    File::delete($file);
})->with([
    'último uso, duas pessoas' => [1, 2],
    'três usos, dez pessoas' => [3, 10],
]);
