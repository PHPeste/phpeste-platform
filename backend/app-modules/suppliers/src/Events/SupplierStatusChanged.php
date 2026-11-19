<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use PHPeste\Audit\Contracts\Auditable;
use PHPeste\Audit\Data\AuditRecord;
use PHPeste\Suppliers\Enums\SupplierStatus;

class SupplierStatusChanged implements Auditable, ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly string $editionId,
        public readonly string $editionSupplierId,
        public readonly ?SupplierStatus $from,
        public readonly SupplierStatus $to,
        public readonly ?int $contractedCents,
        public readonly ?string $actorId,
    ) {}

    public function auditRecord(): AuditRecord
    {
        return new AuditRecord($this->editionId, 'suppliers.status_changed', 'edition_supplier', $this->editionSupplierId, $this->actorId, ['status' => $this->from?->value], ['status' => $this->to->value, 'contracted_cents' => $this->contractedCents]);
    }
}
