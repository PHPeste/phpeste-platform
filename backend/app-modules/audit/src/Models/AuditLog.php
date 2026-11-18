<?php

declare(strict_types=1);

namespace PHPeste\Audit\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string|null $edition_id
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $before
 * @property array<string, mixed>|null $after
 * @property string|null $ip
 * @property string|null $request_id
 * @property Carbon $created_at
 */
#[Fillable(['edition_id', 'actor_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'ip', 'request_id', 'created_at'])]
#[Table(name: 'audit_logs')]
class AuditLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Registros de auditoria não podem ser alterados.'));
        static::deleting(fn (): never => throw new LogicException('Registros de auditoria não podem ser apagados.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
