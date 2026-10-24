<?php
declare(strict_types=1);

namespace Hotelgo\Support;

/** Philippine peso formatting. Loaded by Composer (PSR-4), not by require(). */
final class Money
{
    public static function format($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}
