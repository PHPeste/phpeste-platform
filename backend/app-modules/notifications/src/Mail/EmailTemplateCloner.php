<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

use PHPeste\Community\Contracts\EditionCloner;
use PHPeste\Notifications\Models\EditionEmailTemplate;

class EmailTemplateCloner implements EditionCloner
{
    public function key(): string
    {
        return 'emails';
    }

    public function label(): string
    {
        return 'Modelos de e-mail';
    }

    public function clone(string $sourceEditionId, string $targetEditionId): void
    {
        EditionEmailTemplate::query()->forEdition($sourceEditionId)->get()->each(fn (EditionEmailTemplate $template) => EditionEmailTemplate::create([
            'edition_id' => $targetEditionId,
            'template' => $template->template,
            'subject' => $template->subject,
            'body' => $template->body,
        ]));
    }
}
