<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Data;

final readonly class OrderAttendee
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public function __construct(
        public string $name,
        public string $email,
        public array $answers,
    ) {}
}
