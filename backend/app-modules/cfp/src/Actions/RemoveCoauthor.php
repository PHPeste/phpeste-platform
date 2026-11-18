<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Actions;

use PHPeste\Cfp\Models\Proposal;
use PHPeste\Cfp\Proposals\CfpWindow;

class RemoveCoauthor
{
    public function __construct(private readonly CfpWindow $window) {}

    public function handle(Proposal $proposal, string $userId): Proposal
    {
        $this->window->ensureEditable($proposal->edition_id);

        $proposal->speakers()->where('user_id', $userId)->where('is_owner', false)->delete();

        return $proposal->load('speakers');
    }
}
