<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Events\CfpClosed;
use PHPeste\Cfp\Events\CfpOpened;
use PHPeste\Cfp\Models\CfpSetting;
use PHPeste\Community\Contracts\EditionDirectory;
use PHPeste\Community\Enums\EditionStatus;

class SyncCfpStatus
{
    public function __construct(private readonly EditionDirectory $editions) {}

    public function status(CfpSetting $settings): CfpStatus
    {
        $edition = $this->editions->summary($settings->edition_id);

        return $settings->statusFor(($edition['status'] ?? null) === EditionStatus::CfpOpen->value);
    }

    public function handle(string $editionId): ?CfpStatus
    {
        $settings = CfpSetting::query()->forEdition($editionId)->first();

        if ($settings === null) {
            return null;
        }

        $previous = $settings->announced_status;
        $current = $this->status($settings);

        if ($previous === $current) {
            return $current;
        }

        $settings->update(['announced_status' => $current]);

        if ($current === CfpStatus::Open) {
            event(new CfpOpened($editionId));
        } elseif ($previous === CfpStatus::Open) {
            event(new CfpClosed($editionId));
        }

        return $current;
    }
}
