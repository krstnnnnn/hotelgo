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
        return $this->db->table($this->table)->order_by('id', 'DESC')->limit($limit)->get_all();
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
        return $this->delete($id) > 0;
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
        if (!$n) {
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
            . setting('motel_name') . ', ' . setting('motel_address') . "\nDirections: " . maps_link() . "\nYour ticket and receipt: " . $link . "\n\nA calendar file is attached. Show your booking reference and a valid ID at check-in.\n";
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
            . '<div style="background:#fff;border-radius:0 0 20px 20px;padding:26px"><div style="font-size:12px;letter-spacing:.12em;color:#0E7C86;font-weight:700">' . e(strtoupper(setting('motel_name'))) . '</div><div style="font-size:40px;font-weight:800;margin:4px 0">Room ' . e($b['room_no']) . '</div><div style="color:#4F6574">' . e($b['type_name']) . '</div>'
            . '<table style="width:100%;border-collapse:collapse;margin-top:14px;border-top:1px solid #DCE6EB">' . $row('Booking reference', $b['booking_ref']) . $row('Check-in', fmt_dt($b['start_at'])) . $row('Check-out', fmt_dt($b['end_at'])) . $row('Stay', stay_label($b) . ' · ' . (int) $b['guests'] . ' guests') . $row('Paid', money($b['total'])) . '</table>'
            . '<p style="margin:20px 0 8px"><a href="' . e($link) . '" style="display:inline-block;background:#0E7C86;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px">View ticket &amp; receipt</a> '
            . '<a href="' . e(maps_link()) . '" style="display:inline-block;background:#0B2233;color:#fff;text-decoration:none;font-weight:700;padding:12px 20px;border-radius:12px">Get directions</a></p>'
            . '<p style="color:#4F6574;font-size:13px">' . e(setting('motel_address')) . '<br>A calendar file (reminder 30 minutes before check-in) is attached. Show your booking reference and a valid ID at check-in.</p></div></div></body></html>';
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
