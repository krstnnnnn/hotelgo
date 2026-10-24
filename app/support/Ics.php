<?php
declare(strict_types=1);

namespace Hotelgo\Support;

/** Builds the calendar (.ics) file that guests download and that is attached to the confirmation email. */
final class Ics
{
    public static function booking(array $booking, string $motelName, string $motelAddress): string
    {
        $esc = static fn($s) => preg_replace('/([,;\\\\])/', '\\\\$1', str_replace(["\r", "\n"], ' ', (string) $s));
        $utc = static fn($s) => gmdate('Ymd\\THis\\Z', strtotime((string) $s));

        return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HOTELGO//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\n"
            . 'UID:' . $booking['booking_ref'] . "@hotelgo\r\n"
            . 'DTSTAMP:' . gmdate('Ymd\\THis\\Z') . "\r\n"
            . 'SEQUENCE:' . max(0, intdiv(time() - 1700000000, 60)) . "\r\n"
            . 'DTSTART:' . $utc($booking['start_at']) . "\r\n"
            . 'DTEND:' . $utc($booking['end_at']) . "\r\n"
            . "STATUS:CONFIRMED\r\n"
            . 'DESCRIPTION:' . $esc('Booking ' . $booking['booking_ref'] . '. Show your reference and a valid ID at check-in.') . "\r\n"
            . 'SUMMARY:' . $esc('HOTELGO · Room ' . $booking['room_no']) . "\r\n"
            . 'LOCATION:' . $esc($motelName . ', ' . $motelAddress) . "\r\n"
            . "BEGIN:VALARM\r\nTRIGGER:-PT30M\r\nACTION:DISPLAY\r\nDESCRIPTION:Check-in in 30 minutes\r\nEND:VALARM\r\n"
            . "END:VEVENT\r\nEND:VCALENDAR\r\n";
    }
}
