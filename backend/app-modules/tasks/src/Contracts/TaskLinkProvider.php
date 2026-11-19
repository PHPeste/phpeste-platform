<?php

declare(strict_types=1);

namespace PHPeste\Tasks\Contracts;

/**
 * Coisa de outro módulo a que uma tarefa pode se ligar (fornecedor,
 * patrocinador, sessão...). O módulo dono implementa e registra com
 * `$app->tag([...], TaskLinkProvider::TAG)`; o Tasks só guarda o tipo e o id
 * e pergunta o nome na hora de mostrar.
 */
interface TaskLinkProvider
{
    public const string TAG = 'tasks.link-providers';

    /**
     * Identificador guardado em `tasks.link_type` (ex.: `supplier`).
     */
    public function key(): string;

    public function label(): string;

    /**
     * @param  list<string>  $ids
     * @return array<string, string> id => nome, só dos que existem nesta edição
     */
    public function names(string $editionId, array $ids): array;

    /**
     * @return list<array{id: string, name: string}>
     */
    public function options(string $editionId): array;
}
