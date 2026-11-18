<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

final readonly class RenderedEmail
{
    public function __construct(
        public string $fromName,
        public string $subject,
        public string $html,
        public string $text,
        public ?string $unsubscribeUrl,
    ) {}
}
