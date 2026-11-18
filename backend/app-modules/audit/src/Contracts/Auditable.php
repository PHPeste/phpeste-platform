<?php

declare(strict_types=1);

namespace PHPeste\Audit\Contracts;

use PHPeste\Audit\Data\AuditRecord;

interface Auditable
{
    public function auditRecord(): AuditRecord;
}
