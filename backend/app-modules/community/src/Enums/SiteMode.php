<?php

declare(strict_types=1);

namespace PHPeste\Community\Enums;

enum SiteMode: string
{
    case Platform = 'platform';
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Platform => 'Site da plataforma',
            self::External => 'Site próprio da comunidade',
        };
    }
}
