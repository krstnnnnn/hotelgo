<?php
declare(strict_types=1);

namespace Hotelgo\Support;

final class Csv
{
    /** Stops CSV formula injection: a cell that starts with = + - @ is prefixed with a quote. */
    public static function safe($value): string
    {
        $value = (string) $value;
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}
