<?php

declare(strict_types=1);

namespace PHPeste\Audit\Data;

use Carbon\CarbonImmutable;

/**
 * Uma linha da trilha de auditoria, como os outros módulos enxergam.
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public string $id,
        public string $action,
        public ?string $actorId,
        public ?array $before,
        public ?array $after,
        public CarbonImmutable $createdAt,
    ) {}
}
