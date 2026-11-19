<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Imports;

use PHPeste\Ticketing\Enums\ImportRowOutcome;

final readonly class RowEvaluation
{
    public function __construct(
        public ImportRowOutcome $outcome,
        public ?ImportRow $row = null,
        public ?string $reason = null,
    ) {}

    public static function import(ImportRow $row): self
    {
        return new self(ImportRowOutcome::Import, $row);
    }

    public static function duplicate(string $reason): self
    {
        return new self(ImportRowOutcome::Duplicate, null, $reason);
    }

    public static function rejected(string $reason): self
    {
        return new self(ImportRowOutcome::Rejected, null, $reason);
    }
}
