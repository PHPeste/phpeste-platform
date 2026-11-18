<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionCloner
{
    public const string TAG = 'community.edition-cloners';

    public function key(): string;

    public function label(): string;

    public function clone(string $sourceEditionId, string $targetEditionId): void;
}
