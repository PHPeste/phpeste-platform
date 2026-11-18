<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Cfp\Enums\ConfirmationStatus;

class SpeakerConfirmationChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $proposalId,
        public readonly ConfirmationStatus $from,
        public readonly ConfirmationStatus $to,
        public readonly ?string $actorId,
    ) {}
}
