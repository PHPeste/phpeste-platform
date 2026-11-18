<?php

declare(strict_types=1);

namespace PHPeste\Branding\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ThemePublished implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $editionId) {}
}
