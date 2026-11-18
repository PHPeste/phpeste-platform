<?php

declare(strict_types=1);

namespace PHPeste\Audit\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Apaga da trilha de auditoria os registros mais antigos que a retenção configurada')]
#[Signature('audit:prune')]
class PruneAuditLogsCommand extends Command
{
    public function handle(): int
    {
        $days = (int) config('app.audit_retention_days');

        if ($days < 1) {
            $this->warn('Retenção desligada (AUDIT_RETENTION_DAYS < 1); nada foi apagado.');

            return self::SUCCESS;
        }

        $deleted = DB::table('audit_logs')->where('created_at', '<', now()->subDays($days))->delete();

        $this->info("{$deleted} registros com mais de {$days} dias apagados.");

        return self::SUCCESS;
    }
}
