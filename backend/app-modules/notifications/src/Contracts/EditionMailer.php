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

    /**
     * O e-mail como sairia, com o tema e o texto da edição, sem enviar.
     *
     * @param  array<string, string>  $variables
     * @return array{subject: string, html: string, text: string}
     */
    public function preview(string $editionId, EmailTemplate $template, string $email, array $variables = [], ?string $actionUrl = null): array;
}
