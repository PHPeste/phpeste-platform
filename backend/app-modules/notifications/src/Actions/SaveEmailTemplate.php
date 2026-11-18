<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Actions;

use PHPeste\Notifications\Enums\EmailTemplate;
use PHPeste\Notifications\Models\EditionEmailTemplate;

class SaveEmailTemplate
{
    public function handle(EmailTemplate $template, string $subject, string $body): EditionEmailTemplate
    {
        return EditionEmailTemplate::query()->updateOrCreate(
            ['template' => $template],
            ['subject' => $subject, 'body' => $body],
        );
    }
}
