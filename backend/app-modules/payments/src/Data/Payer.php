<?php

declare(strict_types=1);

namespace PHPeste\Payments\Data;

final readonly class Payer
{
    public function __construct(
        public string $name,
        public string $email,
        public string $document,
    ) {}
}
