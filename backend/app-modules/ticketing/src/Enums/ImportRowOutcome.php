<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum ImportRowOutcome: string
{
    case Import = 'import';
    case Duplicate = 'duplicate';
    case Rejected = 'rejected';
}
