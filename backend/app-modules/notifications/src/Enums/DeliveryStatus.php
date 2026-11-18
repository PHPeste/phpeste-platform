<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Enums;

enum DeliveryStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
}
