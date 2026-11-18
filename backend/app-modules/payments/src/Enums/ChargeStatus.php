<?php

declare(strict_types=1);

namespace PHPeste\Payments\Enums;

enum ChargeStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';
}
