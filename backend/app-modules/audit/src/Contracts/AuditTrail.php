<?php

declare(strict_types=1);

namespace PHPeste\Audit\Contracts;

use PHPeste\Audit\Data\AuditEntry;

/**
 * Leitura da auditoria de um registro, para montar o histórico na tela
 * do próprio módulo sem que ele consulte a tabela de auditoria.
 */
interface AuditTrail
{
    /**
     * Do mais antigo para o mais recente.
     *
     * @return list<AuditEntry>
     */
    public function forSubject(string $editionId, string $subjectType, string $subjectId, int $limit = 200): array;
}
