<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Ready = 'ready';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Aguardando mapeamento',
            self::Ready => 'Pronta para importar',
            self::Processing => 'Importando',
            self::Completed => 'Concluída',
            self::Failed => 'Interrompida',
        };
    }

    public function editable(): bool
    {
        return in_array($this, [self::Uploaded, self::Ready], true);
    }
}
