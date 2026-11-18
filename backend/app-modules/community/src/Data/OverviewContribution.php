<?php

declare(strict_types=1);

namespace PHPeste\Community\Data;

use PHPeste\Community\Enums\EditionPermission;

final readonly class OverviewContribution
{
    /**
     * @param  array<string, mixed>  $indicators
     * @param  list<array{key: string, title: string, tone: string, tag: string, action_label: string, action_path: string, priority: int, permission: EditionPermission}>  $pending
     * @param  list<array{key: string, title: string, description: string, date: string}>  $milestones
     * @param  array<string, mixed>  $sections
     */
    public function __construct(
        public array $indicators = [],
        public array $pending = [],
        public array $milestones = [],
        public array $sections = [],
    ) {}
}
