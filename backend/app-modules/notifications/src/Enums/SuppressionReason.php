<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Enums;

enum SuppressionReason: string
{
    case Bounced = 'bounced';
    case Complained = 'complained';

    public function label(): string
    {
        return match ($this) {
            self::Bounced => 'Bounce permanente (endereço não existe ou recusa)',
            self::Complained => 'Marcou o e-mail como spam',
        };
    }
}
