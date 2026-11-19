<?php

declare(strict_types=1);

namespace PHPeste\Sponsors\Enums;

enum AttachmentKind: string
{
    case Contract = 'contract';
    case Logo = 'logo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contrato',
            self::Logo => 'Logo',
            self::Other => 'Outro',
        };
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Contract => ['pdf', 'doc', 'docx', 'odt'],
            self::Logo => ['svg', 'pdf', 'eps', 'ai', 'png'],
            self::Other => ['pdf', 'doc', 'docx', 'odt', 'png', 'jpg', 'jpeg', 'svg', 'xlsx', 'ods', 'zip'],
        };
    }
}
