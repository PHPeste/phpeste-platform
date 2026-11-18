<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Listeners;

use PHPeste\Cfp\Actions\SyncCfpStatus;
use PHPeste\Community\Events\EditionStatusChanged;

class SyncCfpOnEditionStatusChange
{
    public function __construct(private readonly SyncCfpStatus $sync) {}

    public function handle(EditionStatusChanged $event): void
    {
        $this->sync->handle($event->editionId);
    }
}
