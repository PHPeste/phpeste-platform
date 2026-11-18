<?php

declare(strict_types=1);

namespace PHPeste\Notifications\Mail;

use Illuminate\Support\Facades\Date;

final class EditionDates
{
    private const array MONTHS = [
        1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
        'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
    ];

    public static function format(?string $startsOn, ?string $endsOn): string
    {
        if ($startsOn === null) {
            return 'data a definir';
        }

        $start = Date::parse($startsOn);
        $end = $endsOn === null ? $start : Date::parse($endsOn);

        if ($start->isSameDay($end)) {
            return "{$start->day} de ".self::MONTHS[$start->month];
        }

        if ($start->isSameMonth($end)) {
            $separator = $start->diffInDays($end) === 1.0 ? 'e' : 'a';

            return "{$start->day} {$separator} {$end->day} de ".self::MONTHS[$end->month];
        }

        return "{$start->day} de ".self::MONTHS[$start->month]." a {$end->day} de ".self::MONTHS[$end->month];
    }
}
