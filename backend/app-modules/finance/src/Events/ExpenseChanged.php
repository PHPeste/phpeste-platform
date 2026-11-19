<?php

declare(strict_types=1);

namespace PHPeste\Finance\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;

/**
 * Despesa criada, alterada ou removida (`action`: created, updated, deleted).
 */
class ExpenseChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function __construct(
        public readonly string $editionId,
        public readonly string $expenseId,
        public readonly string $action,
        public readonly ?array $before,
        public readonly ?array $after,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'finance.expense_'.$this->action, 'expense', $this->expenseId, $this->actorId, $this->before, $this->after);
    }
}
