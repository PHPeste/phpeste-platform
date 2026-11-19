<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum AttachmentKind: string
{
    case Quote = 'quote';
    case Contract = 'contract';
    case Invoice = 'invoice';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Quote => 'Orçamento',
            self::Contract => 'Contrato',
            self::Invoice => 'Nota fiscal',
            self::Other => 'Outro',
        };
    }

    /**
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Quote, self::Contract => ['pdf', 'doc', 'docx', 'odt', 'png', 'jpg', 'jpeg'],
            self::Invoice => ['pdf', 'xml', 'png', 'jpg', 'jpeg'],
            self::Other => ['pdf', 'doc', 'docx', 'odt', 'png', 'jpg', 'jpeg', 'xlsx', 'ods', 'zip'],
        };
    }
}
