<?php

declare(strict_types=1);

namespace PHPeste\Ticketing\Orders;

final class Split
{
    /**
     * @return list<int>
     */
    public static function evenly(int $amount, int $parts): array
    {
        $base = intdiv($amount, $parts);
        $rest = $amount % $parts;

        return array_map(fn (int $index): int => $base + ($index < $rest ? 1 : 0), range(0, $parts - 1));
    }

    public static function fee(int $amount, int $basisPoints): int
    {
        return $basisPoints === 0 || $amount === 0 ? 0 : intdiv($amount * $basisPoints + 9_999, 10_000);
    }
}
