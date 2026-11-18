<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Models\CfpTrack;

class DeleteTrack
{
    public function handle(CfpTrack $track): void
    {
        $track->delete();
    }
}
