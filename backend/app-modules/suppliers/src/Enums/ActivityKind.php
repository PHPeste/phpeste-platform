<?php

declare(strict_types=1);

namespace PHPeste\Suppliers\Enums;

enum ActivityKind: string
{
    case Note = 'note';
    case Status = 'status';
    case Quote = 'quote';
    case Attachment = 'attachment';
}
