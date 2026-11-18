<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Contracts;

use PHPeste\Notifications\Enums\EmailTemplate;

interface EditionMailer
{
    /**
     * @param  array<string, string>  $variables
     */
    public function send(string $editionId, EmailTemplate $template, string $email, string $name, array $variables = [], ?string $actionUrl = null): string;
}
