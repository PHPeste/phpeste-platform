<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Actions;

use Illuminate\Support\Carbon;
use PHPeste\Suppliers\Enums\ActivityKind;
use PHPeste\Suppliers\Enums\ContactChannel;
use PHPeste\Suppliers\Models\EditionSupplier;
use PHPeste\Suppliers\Models\SupplierActivity;

class RecordActivity
{
    public function handle(EditionSupplier $link, ActivityKind $kind, string $summary, ?string $userId, ?ContactChannel $channel = null, ?Carbon $happenedAt = null): SupplierActivity
    {
        return SupplierActivity::create([
            'edition_id' => $link->edition_id,
            'edition_supplier_id' => $link->id,
            'kind' => $kind,
            'channel' => $channel,
            'summary' => $summary,
            'user_id' => $userId,
            'happened_at' => $happenedAt ?? now(),
        ]);
    }
}
