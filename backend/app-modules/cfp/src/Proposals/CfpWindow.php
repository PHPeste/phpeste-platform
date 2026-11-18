<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Proposals;

use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Actions\SyncCfpStatus;
use PHPeste\Cfp\Enums\CfpStatus;
use PHPeste\Cfp\Models\CfpSetting;

class CfpWindow
{
    public function __construct(private readonly SyncCfpStatus $sync) {}

    public function settings(string $editionId): CfpSetting
    {
        return CfpSetting::query()->forEdition($editionId)->first() ?? new CfpSetting(['edition_id' => $editionId]);
    }

    public function status(string $editionId): CfpStatus
    {
        return $this->sync->status($this->settings($editionId));
    }

    public function ensureEditable(string $editionId): void
    {
        if (!in_array($this->status($editionId), [CfpStatus::Scheduled, CfpStatus::Open], true)) {
            throw ValidationException::withMessages([
                'cfp' => 'O CFP desta edição está fechado. Fale com a organização para alterar a proposta.',
            ]);
        }
    }

    public function ensureOpen(string $editionId): void
    {
        if ($this->status($editionId) !== CfpStatus::Open) {
            throw ValidationException::withMessages([
                'cfp' => 'O CFP desta edição não está aberto para envio.',
            ]);
        }
    }
}
