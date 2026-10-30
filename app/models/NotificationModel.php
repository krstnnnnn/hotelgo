<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Everything the system says to guests: SMS (optional, paid) and email (free Gmail relay). Table: notifications.
| Every message is saved here with its status: sent / logged (demo, nothing was sent) / failed / retried.
| Sending is done by the library app/libraries/Messenger.php. A failed SMS or email NEVER stops a booking.
| Sign-up and password-reset codes go to the guest's EMAIL (free). No SMS needed.
*/
class NotificationModel extends Model
{
    protected $table = 'notifications';

    private function messenger()
    {
        return $this->call->library('messenger');
    }
    public function email_enabled(): bool
    {
        return $this->messenger()->email_enabled();
    }

    /* ===================================== read / delete ===================================== */
    public function recent(int $limit = 200): array
    {
        $this->db->table('notifications n')->select('n.id, n.booking_id, n.user_id, n.channel, n.recipient, n.subject, n.message, n.status, n.error, n.provider_ref, n.created_at, h.name AS hotel_name')->left_join('bookings b', 'b.id = n.booking_id')->left_join('hotels h', 'h.id = b.hotel_id');
        \Hotelgo\Support\Scope::limit($this->db, 'b.hotel_id');                 // hotel staff only see messages about their own bookings
        return $this->db->order_by('n.id', 'DESC')->limit($limit)->get_all();
    }
    /* Is this message about a booking of the current hotel? (platform admin: always) */
    private function in_scope(int $id): bool
    {
        if (\Hotelgo\Support\Scope::hotel() === null) return true;
        $n = $this->db->table($this->table)->select('booking_id')->where('id', $id)->get();
        $b = $n && $n['booking_id'] ? $this->db->table('bookings')->select('hotel_id')->where('id', $n['booking_id'])->get() : null;
        return $b && \Hotelgo\Support\Scope::allows($b['hotel_id']);
    }
    public function for_booking(int $bookingId): array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->order_by('id', 'DESC')->get_all();
    }
    /* The newest message of one channel (sms / email) for a booking */
    public function latest(int $bookingId, string $channel): ?array
    {
        return $this->db->table($this->table)->where('booking_id', $bookingId)->where('channel', $channel)->order_by('id', 'DESC')->limit(1)->get() ?: null;
    }
    public function remove(int $id): bool
    {
        return $this->in_scope($id) && $this->delete($id) > 0;
    }

    /* ===================================== send one message (returns the id of the saved row) ===================================== */
    public function send(?int $bookingId, ?int $userId, string $channel, string $to, string $subject, string $message, array $opts = []): int
    {
        [$status, $ref, $error] = $this->messenger()->deliver($channel, $to, $subject, $message, $opts);
        return (int) $this->insert([
            'booking_id' => $bookingId, 'user_id' => $userId, 'channel' => $channel, 'recipient' => $to, 'subject' => $subject,
            'message' => $message, 'status' => $status, 'error' => $error, 'provider_ref' => $ref, 'created_at' => now_str(),
        ]);
    }
    /* Try a failed message again */
    public function retry(int $id): bool
    {
        $n = $this->db->table($this->table)->where('id', $id)->where('status', 'failed')->get();
        if (!$n || !$this->in_scope($id)) {
            return false;
        }
        $this->send($n['booking_id'] ? (int) $n['booking_id'] : null, $n['user_id'] ? (int) $n['user_id'] : null, (string) $n['channel'], (string) $n['recipient'], (string) $n['subject'], (string) $n['message']);
        $this->update($id, ['status' => 'retried']);
        return true;
    }

    /* ===================================== the messages the system sends ===================================== */
    private function uid(array $b): ?int
    {
        return $b['user_id'] ? (int) $b['user_id'] : null;
    }

    public function booking_confirmed(array $b): void
    {
        $when = fmt_dt($b['start_at'], 'D j M g:i A') . ' - ' . fmt_dt($b['end_at'], 'g:i A');
        $this->send((int) $b['id'], $this->uid($b), 'sms', $b['guest_mobile'], '', 'HOTELGO: Booking ' . $b['booking_ref'] . ' confirmed. Room ' . $b['room_no'] . ', ' . $when . '. Show this ref at check-in.');
        if ($b['guest_email'] === '') return;
        $link = abs_url('booking/' . $b['booking_ref']);
        $text = "Hi " . $b['guest_name'] . ",\n\nYour HOTELGO booking is confirmed.\n\nReference: " . $b['booking_ref'] . "\nRoom: " . $b['room_no'] . ' (' . $b['type_name'] . ")\nCheck-in: " . fmt_dt($b['start_at']) . "\nCheck-out: " . fmt_dt($b['end_at']) . "\nPaid: " . money($b['total']) . "\n\n"
            . hotel_of($b, 'name') . ', ' . hotel_of($b, 'address') . "\nDirections: " . maps_link($b) . "\nYour ticket and receipt: " . $link . "\n\nA calendar file is attached. Show your booking reference and a valid ID at check-in.\n";
        $this->send((int) $b['id'], $this->uid($b), 'email', $b['guest_email'], 'Your HOTELGO booking is confirmed', $text, [
            'html' => $this->confirmation_html($b, $link),
            'attachments' => [['filename' => 'hotelgo-' . $b['booking_ref'] . '.ics', 'mime' => 'text/calendar', 'content' => base64_encode(ics_content($b))]],
        ]);
    }

    public function confirmation_html(array $b, string $link): string
    {
        $row = fn($k, $v) => '<tr><td style="padding:8px 0;color:#4F6574;font-size:14px">' . e($k) . '</td><td style="padding:8px 0;text-align:right;font-weight:700;font-size:14px">' . e($v) . '</td></tr>';
        return '<!doctype html><html><body style="margin:0;background:#F3F7F9;font-family:Arial,Helvetica,sans-serif;color:#0B2233"><div style="max-width:560px;margin:0 auto;padding:24px">'
            . '<div style="background:#0B2233;border-radius:20px 20px 0 0;padding:26px;color:#fff"><div style="font-size:20px;font-weight:800">HOTEL<span style="color:#F5A524">GO</span></div><div style="font-size:24px;font-weight:800;margin-top:14px">You\'re booked, ' . e(explode(' ', $b['guest_name'])[0]) . '!</div><div style="color:#C5D5DD;margin-top:4px">Payment received. Your room is assigned.</div></div>'
            . '<div style="background:#fff;border-radius:0 0 20px 20px;padding:26px"><div style="font-size:12px;letter-spacing:.12em;color:#0E7C86;font-weight:700">' . e(strtoupper(hotel_of($b, 'name'))) . '</div><div style="font-size:40px;font-weight:800;margin:4px 0">Room ' . e($b['room_no']) . '</div><div style="color:#4F6574">' . e($b['type_name']) . '</div>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:14px;border-top:1px solid #DCE6EB">' . $row('Booking reference', $b['booking_ref']) . $row('Check-in', fmt_dt($b['start_at'])) . $row('Check-out', fmt_dt($b['end_at'])) . $row('Stay', stay_label($b) . ' · ' . (int) $b['guests'] . ' guests') . $row('Paid', money($b['total'])) . '</table>'
            . '<p style="margin:20px 0 8px"><a href="' . e($link) . '" style="display:inline-block;background:#0E7C86;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px">View ticket &amp; receipt</a> '
            . '<a href="' . e(maps_link($b)) . '" style="display:inline-block;background:#0B2233;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px">Get directions</a></p>'
            . '<p style="color:#4F6574;font-size:13px">' . e(hotel_of($b, 'address')) . '<br>A calendar file (reminder 30 minutes before check-in) is attached. Show your booking reference and a valid ID at check-in.</p></div></div></body></html>';
    }

    public function booking_cancelled(array $b): void
    {
        $this->send((int) $b['id'], $this->uid($b), 'sms', $b['guest_mobile'], '', 'HOTELGO: Booking ' . $b['booking_ref'] . ' was cancelled. Any payment will be refunded to your original payment method.');
        if ($b['guest_email'] !== '') $this->send((int) $b['id'], $this->uid($b), 'email', $b['guest_email'], 'Your HOTELGO booking was cancelled', "Hi " . $b['guest_name'] . ",\n\nBooking " . $b['booking_ref'] . " was cancelled. Any payment will be refunded to your original payment method.\n\n" . setting('motel_name') . "\n");
    }
    public function booking_rescheduled(array $b): void
    {
        $this->send((int) $b['id'], $this->uid($b), 'sms', $b['guest_mobile'], '', 'HOTELGO: Booking ' . $b['booking_ref'] . ' moved to ' . fmt_dt($b['start_at'], 'D j M g:i A') . ' - ' . fmt_dt($b['end_at'], 'g:i A') . ', Room ' . $b['room_no'] . '.');
        if ($b['guest_email'] !== '') $this->send((int) $b['id'], $this->uid($b), 'email', $b['guest_email'], 'Your HOTELGO booking was changed', "Hi " . $b['guest_name'] . ",\n\nBooking " . $b['booking_ref'] . " is now:\nRoom " . $b['room_no'] . "\nCheck-in: " . fmt_dt($b['start_at']) . "\nCheck-out: " . fmt_dt($b['end_at']) . "\n");
    }
    /* A guest asked for an extra service: the motel is told by email (and sees it in the console: Requests, bell, badge) */
    public function request_received(array $b, array $r): void
    {
        $hotel = $this->db->table('hotels')->select('email, name')->where('id', (int) ($b['hotel_id'] ?? 0))->get();
        $to = trim((string) ($hotel['email'] ?? ''));
        if ($to === '') return;
        $this->send((int) $b['id'], null, 'email', $to, 'New service request: ' . $r['service'] . ' (Room ' . ($b['room_no'] ?? '?') . ')',
            "A guest asked for a service.\n\nService: " . $r['service'] . "\nNote: " . ($r['note'] ?: '-') . "\nGuest: " . $b['guest_name'] . ' · ' . $b['guest_mobile'] . "\nRoom: " . ($b['room_no'] ?? '?') . ' (' . $b['type_name'] . ")\nBooking: " . $b['booking_ref']
            . "\n\nOpen the staff console > Requests to accept, set a price or decline it.\n");
    }
    /* The motel answered a request: the guest is told */
    public function request_update(array $r, string $reply = ''): void
    {
        $b = $this->db->table('bookings')->select('id, user_id, guest_name, guest_mobile, guest_email, booking_ref')->where('id', (int) $r['booking_id'])->get();
        if (!$b) return;
        $what = ['accepted' => 'was accepted', 'done' => 'is done', 'declined' => 'could not be accepted'][$r['status']] ?? null;
        if ($what === null) return;
        $price = (float) ($r['price'] ?? 0) > 0 && $r['status'] === 'accepted' ? ' Extra charge: ' . money($r['price']) . '. You can pay it at the desk or online on your booking page.' : '';
        $text = 'HOTELGO: Your request "' . $r['service'] . '" for booking ' . $b['booking_ref'] . ' ' . $what . '.' . $price . ($reply !== '' ? ' Note: ' . $reply : '');
        $this->send((int) $b['id'], $this->uid($b), 'sms', $b['guest_mobile'], '', $text);
        if ($b['guest_email'] !== '') $this->send((int) $b['id'], $this->uid($b), 'email', $b['guest_email'], 'Your HOTELGO request ' . $what, "Hi " . $b['guest_name'] . ",\n\n" . substr($text, 9) . "\n\nYour booking page: " . abs_url('booking/' . $b['booking_ref']) . "\n");
    }
    /* sign-up and password-reset codes go to the guest's EMAIL (free). No SMS needed. */
    public function code(int $userId, string $email, string $purpose, string $code): void
    {
        $what = $purpose === 'reset' ? 'password reset' : 'verification';
        $subject = 'Your HOTELGO ' . $what . ' code: ' . $code;
        $text = "Your HOTELGO " . $what . " code is:\n\n    " . $code . "\n\nIt expires in 10 minutes. If you did not ask for it, ignore this email and never share the code.\n";
        $html = '<div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;padding:24px;color:#0B2233"><div style="font-size:20px;font-weight:800">HOTEL<span style="color:#F5A524">GO</span></div><p>Your ' . e($what) . ' code is:</p><div style="font-size:34px;font-weight:800;letter-spacing:6px;background:#F3F7F9;border-radius:12px;padding:14px;text-align:center">' . e($code) . '</div><p style="color:#4F6574;font-size:13px">It expires in 10 minutes. If you did not ask for it, ignore this email and never share the code.</p></div>';
        $this->send(null, $userId, 'email', $email, $subject, $text, ['html' => $html]);
    }
}
