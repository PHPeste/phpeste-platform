<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Http;

use PHPeste\Identity\Contracts\UserAccounts;

/**
 * Nome de responsável e autor, pelo contrato do Identity.
 */
final class People
{
    /**
     * @var array<string, array{id: string, name: string}>
     */
    private array $known = [];

    public function __construct(private readonly UserAccounts $accounts) {}

    /**
     * @param  array<mixed>  $ids  o que vier de um `pluck`, nulos inclusive
     */
    public function load(array $ids): self
    {
        $missing = array_values(array_diff(array_unique(array_filter($ids, is_string(...))), array_keys($this->known)));

        foreach ($this->accounts->profiles($missing) as $id => $profile) {
            $this->known[$id] = ['id' => $profile['id'], 'name' => $profile['name']];
        }

        return $this;
    }

    /**
     * @return array{id: string, name: string}|null
     */
    public function get(?string $id): ?array
    {
        return $id === null ? null : ($this->known[$id] ?? null);
    }
}
