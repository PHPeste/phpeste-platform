<?php

declare(strict_types=1);

namespace PHPeste\Community\Contracts;

interface EditionDirectory
{
    public function isPublished(string $editionId): bool;

    /**
     * @return array{id: string, name: string, starts_on: string|null, ends_on: string|null, city: string|null, state: string|null, timezone: string, status: string}|null
     */
    public function summary(string $editionId): ?array;

    /**
     * @return array{mode: string, external_url: string|null}
     */
    public function site(string $editionId): array;

    /**
     * Comunidade dona da edição: o que é "global" para outro módulo (ex.:
     * fornecedores) é global dentro da comunidade, nunca entre comunidades.
     */
    public function communityId(string $editionId): ?string;

    /**
     * @param  list<string>  $editionIds
     * @return array<string, array{id: string, name: string, starts_on: string|null}>
     */
    public function names(array $editionIds): array;
}
