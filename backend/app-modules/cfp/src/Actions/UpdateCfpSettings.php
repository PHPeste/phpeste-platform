<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Models\CfpSetting;

class UpdateCfpSettings
{
    public function __construct(private readonly SyncCfpStatus $sync) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(CfpSetting $settings, array $attributes): CfpSetting
    {
        $settings->update($attributes);
        $this->sync->handle($settings->edition_id);

        return $settings->refresh();
    }
}
