<?php
declare(strict_types=1);

namespace Hotelgo\Support;

/** Philippine mobile numbers and privacy masking. */
final class Phone
{
    /** Returns +639XXXXXXXXX or null when the number is not a valid PH mobile number. */
    public static function normalize(string $number): ?string
    {
        $number = preg_replace('/[\s\-()]/', '', $number);
        if (preg_match('/^(\+63|63|0)(9\d{9})$/', $number, $m)) {
            return '+63' . $m[2];
        }
        return null;
    }

    public static function mask(?string $number): string
    {
        return $number && strlen($number) > 6 ? substr($number, 0, 6) . ' ••• ' . substr($number, -4) : '';
    }

    public static function maskEmail(?string $email): string
    {
        if (!$email || !str_contains($email, '@')) {
            return '';
        }
        [$name, $domain] = explode('@', $email, 2);
        return substr($name, 0, 2) . '•••@' . $domain;
    }
}
