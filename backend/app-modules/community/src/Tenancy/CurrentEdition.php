<?php

declare(strict_types=1);

namespace PHPeste\Community\Tenancy;

use Closure;

final class CurrentEdition
{
    private ?string $editionId = null;

    public function set(string $editionId): void
    {
        $this->editionId = $editionId;
    }

    public function clear(): void
    {
        $this->editionId = null;
    }

    public function id(): ?string
    {
        return $this->editionId;
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    public function run(string $editionId, Closure $callback): mixed
    {
        $previous = $this->editionId;
        $this->editionId = $editionId;

        try {
            return $callback();
        } finally {
            $this->editionId = $previous;
        }
    }
}
