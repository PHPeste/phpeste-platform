<?php

declare(strict_types=1);

namespace PHPeste\Audit\Data;

final readonly class AuditRecord
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public ?string $editionId,
        public string $action,
        public string $subjectType,
        public string $subjectId,
        public ?string $actorId = null,
        public ?array $before = null,
        public ?array $after = null,
    ) {}
}
