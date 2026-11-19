<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Enums;

enum OrderSource: string
{
    case Checkout = 'checkout';
    case Import = 'import';
}
