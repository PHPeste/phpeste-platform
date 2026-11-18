<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Models\CfpSetting;

class ResolveCfpSettings
{
    public function handle(): CfpSetting
    {
        return CfpSetting::query()->firstOrCreate([]);
    }
}
