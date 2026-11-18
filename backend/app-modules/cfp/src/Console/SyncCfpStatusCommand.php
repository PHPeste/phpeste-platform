<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PHPeste\Cfp\Actions\SyncCfpStatus;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Models\CfpSetting;

#[Description('Dispara CfpOpened e CfpClosed para as edições cuja janela do CFP virou')]
#[Signature('cfp:sync-status')]
class SyncCfpStatusCommand extends Command
{
    public function handle(SyncCfpStatus $sync): int
    {
        CfpSetting::query()
            ->withoutGlobalScopes()
            ->pluck('edition_id')
            ->each(fn (string $editionId): ?CfpStatus => $sync->handle($editionId));

        return self::SUCCESS;
    }
}
