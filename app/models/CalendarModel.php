<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Google Calendar sync of bookings. It writes the columns bookings.gcal_event_id / gcal_synced_at.
| The calls to Google are made by the library app/libraries/Google_calendar.php.
|
| One rule decides everything (sync): a booking that is paid (confirmed, checked in, checked out) must be in the hotel's calendar,
| every other booking (waiting for payment, cancelled, expired) must not. So the same call is used after a payment, a cancel,
| a new time, a new room, a check-in and by the "Send upcoming bookings" button. It can be repeated safely.
| A failure never stops a booking: the staff only see it when they press Test / Send in the console.
*/
class CalendarModel extends Model
{
    protected $table = 'bookings';

    const IN_CALENDAR = ['confirmed', 'checked_in', 'checked_out'];
    const COLORS      = ['confirmed' => '7', 'checked_in' => '10', 'checked_out' => '8'];     // Google event colours: blue, green, grey

    public $error = '';

    private function google()
    {
        return $this->call->library('google_calendar');
    }
    public function enabled(): bool
    {
        return $this->google()->configured();
    }

    /* A booking with what the calendar needs (no hotel filter: the system itself is writing, not a person) */
    private function booking(int $id): ?array
    {
        return $this->db->table('bookings b')
            ->select('b.id, b.booking_ref, b.status, b.stay_type, b.hours, b.start_at, b.end_at, b.guests, b.guest_name, b.guest_mobile, b.guest_email, b.total, b.hotel_id, b.gcal_event_id, '
                . 't.name AS type_name, r.room_no, h.name AS hotel_name, h.address AS hotel_address, h.calendar_id')
            ->join('room_types t', 't.id = b.room_type_id')
            ->left_join('rooms r', 'r.id = b.room_id')
            ->left_join('hotels h', 'h.id = b.hotel_id')
            ->where('b.id', $id)->get() ?: null;
    }

    /* The Google event of a booking */
    private function event(array $b): array
    {
        $stay = $b['stay_type'] === 'hourly' ? (int) $b['hours'] . '-hour stay' : 'Overnight stay';
        $tz   = date_default_timezone_get();
        return [
            'summary'     => 'Room ' . ($b['room_no'] ?: '?') . ' · ' . $b['guest_name'] . ' (' . ($b['stay_type'] === 'hourly' ? (int) $b['hours'] . 'h' : 'overnight') . ')',
            'location'    => trim($b['hotel_name'] . ', ' . $b['hotel_address'], ', '),
            'description' => "HOTELGO booking " . $b['booking_ref'] . "\n" . $b['type_name'] . ' · ' . $stay . "\nGuest: " . $b['guest_name'] . ' · ' . $b['guest_mobile']
                . ($b['guest_email'] ? ' · ' . $b['guest_email'] : '') . "\nGuests: " . (int) $b['guests'] . "\nTotal: PHP " . number_format((float) $b['total'], 2)
                . "\nStatus: " . str_replace('_', ' ', $b['status']),
            'start'       => ['dateTime' => date('c', strtotime($b['start_at'])), 'timeZone' => $tz],
            'end'         => ['dateTime' => date('c', strtotime($b['end_at'])), 'timeZone' => $tz],
            'colorId'     => self::COLORS[$b['status']] ?? '7',
            'reminders'   => ['useDefault' => false, 'overrides' => [['method' => 'popup', 'minutes' => 30]]],
            'extendedProperties' => ['private' => ['hotelgo_booking' => $b['booking_ref']]],
        ];
    }

    private function stamp(int $id, ?string $eventId): void
    {
        $this->db->table($this->table)->where('id', $id)->update(['gcal_event_id' => $eventId, 'gcal_synced_at' => $eventId ? now_str() : null]);
    }

    /* Put one booking right in the calendar. Returns ['state' => added | removed | skipped | failed, 'message' => ...]. Never throws. */
    public function sync(int $id): array
    {
        try {
            $g = $this->google();
            if (!$g->configured()) return ['state' => 'skipped', 'message' => 'Google Calendar is not connected.'];
            $b = $this->booking($id);
            if (!$b) return ['state' => 'skipped', 'message' => 'Booking not found.'];
            $cal = $g->calendar_for($b);
            if ($cal === '') return ['state' => 'skipped', 'message' => 'This hotel has no Google Calendar ID.'];
            $eventId = $g->event_id($b['booking_ref']);

            if (in_array($b['status'], self::IN_CALENDAR, true)) {
                if (!$g->save_event($cal, $eventId, $this->event($b))) { $this->error = $g->error; error_log('HOTELGO calendar: ' . $g->error); return ['state' => 'failed', 'message' => $g->error]; }
                $this->stamp($id, $eventId);
                return ['state' => 'added', 'message' => 'In the calendar.'];
            }
            if (!empty($b['gcal_event_id'])) {
                if (!$g->delete_event($cal, $eventId)) { $this->error = $g->error; error_log('HOTELGO calendar: ' . $g->error); return ['state' => 'failed', 'message' => $g->error]; }
                $this->stamp($id, null);
                return ['state' => 'removed', 'message' => 'Removed from the calendar.'];
            }
            return ['state' => 'skipped', 'message' => 'Nothing to do.'];
        } catch (\Throwable $e) {
            error_log('HOTELGO calendar: ' . $e->getMessage());
            return ['state' => 'failed', 'message' => 'Calendar sync failed.'];
        }
    }

    /* Test one calendar. Returns [ok, calendar name or reason] */
    public function test(string $calendarId): array
    {
        $g = $this->google();
        if (!$g->configured()) return [false, 'Google Calendar is not connected on the server (GOOGLE_SERVICE_ACCOUNT_JSON is missing).'];
        $name = $g->check($calendarId);
        return $name !== '' ? [true, $name] : [false, $g->error];
    }

    /* Send every paid booking that is not over yet (one hotel, or all hotels the person may see). Returns [sent, failed, first error] */
    public function sync_upcoming(int $hotelId = 0): array
    {
        $this->db->table('bookings b')->select('b.id')->in('b.status', self::IN_CALENDAR)->where('b.end_at', '>=', now_str());
        if ($hotelId > 0) $this->db->where('b.hotel_id', $hotelId);
        \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id');
        $sent = 0; $failed = 0; $why = '';
        foreach ($this->db->order_by('b.start_at', 'ASC')->limit(300)->get_all() as $row) {
            $r = $this->sync((int) $row['id']);
            if ($r['state'] === 'added') $sent++;
            elseif ($r['state'] === 'failed') { $failed++; $why = $why ?: $r['message']; }
        }
        return [$sent, $failed, $why];
    }

    /* How many bookings of a hotel are in its calendar now */
    public function count_for_hotel(int $hotelId): int
    {
        return $this->db->table($this->table)->where('hotel_id', $hotelId)->where_not_null('gcal_event_id')->count();
    }
}
