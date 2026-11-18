<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EditionEmailTemplate;

class RestoreDefaultEmailTemplate
{
    public function handle(EmailTemplate $template): void
    {
        EditionEmailTemplate::query()->where('template', $template)->delete();
    }
}
