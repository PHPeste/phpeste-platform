<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class CfpOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly string $editionId) {}
}
