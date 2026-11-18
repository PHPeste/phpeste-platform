<?php

declare(strict_types=1);

namespace PHPeste\Community\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Community\Enums\EditionStatus;

class EditionStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly EditionStatus $from,
        public readonly EditionStatus $to,
    ) {}
}
