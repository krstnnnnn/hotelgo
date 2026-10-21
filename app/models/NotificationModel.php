<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Email (+ optional SMS).
|   EMAIL_PROVIDER=appsscript -> FREE: your own Gmail through a small Google Apps Script web app (works on Render free, no domain needed)
|   EMAIL_PROVIDER=resend     -> Resend (free plan, but you need your own verified domain to email other people)
|   SMS_PROVIDER=semaphore    -> real SMS through Semaphore (paid, optional; off by default)
|   anything else             -> the message is only SAVED in the Notifications page ("logged" = demo, nothing is sent)
| Sign-up / password-reset codes are sent by EMAIL (no SMS needed).
| A failed SMS or email NEVER stops a booking. It is saved with status "failed" and the reason.
*/
class NotificationModel extends Model
{
    protected $table = 'notifications';

    /* =============== Semaphore (SMS) =============== */
    public function semaphore_request(string $to, string $message, ?string $otpCode = null): array
    {
        $params = ['apikey' => env_get('SMS_API_KEY'), 'number' => to_local_mobile($to), 'message' => $message];
        if (env_get('SMS_SENDER') !== '') $params['sendername'] = env_get('SMS_SENDER');      // optional: Semaphore uses your default sender name when left out
        $route = 'messages';
        if ($otpCode !== null) {                                                               // OTP route: our own code is passed in "code", {otp} marks where it goes
            $route = 'otp'; $params['code'] = $otpCode; $params['message'] = str_replace($otpCode, '{otp}', $message);
        }
        return ['url' => 'https://api.semaphore.co/api/v4/' . $route, 'headers' => ['Content-Type: application/x-www-form-urlencoded'], 'body' => http_build_query($params)];
    }
    /* returns [status, provider message id, error text] */
    public function semaphore_parse(int $http, string $body): array
    {
        $j = json_decode($body, true);
        if ($http === 429) return ['failed', null, 'Semaphore rate limit reached. Try again in a minute.'];
        if ($http >= 200 && $http < 300 && is_array($j) && isset($j[0]['message_id'])) {
            foreach ($j as $m) {
                $st = strtolower((string) ($m['status'] ?? ''));
                if ($st === 'failed' || $st === 'refunded') return ['failed', (string) $m['message_id'], 'Semaphore marked the message as ' . $st . '.'];
            }
            return ['sent', (string) $j[0]['message_id'], null];
        }
        return ['failed', null, mb_substr('Semaphore error (HTTP ' . $http . '): ' . trim(is_array($j) ? json_encode($j) : $body), 0, 240)];
    }

    /* =============== Google Apps Script relay (free email from your own Gmail) =============== */
    public function appsscript_request(string $to, string $subject, string $text, string $html = '', array $attachments = []): array
    {
        $files = [];
        foreach ($attachments as $a) $files[] = ['filename' => $a['filename'], 'mime' => $a['mime'] ?? 'application/octet-stream', 'content' => $a['content']];
        $payload = ['secret' => env_get('APPS_SCRIPT_SECRET'), 'to' => $to, 'subject' => $subject, 'text' => $text, 'html' => $html, 'attachments' => $files, 'fromName' => env_get('MAIL_FROM_NAME', 'HOTELGO')];
        return ['url' => env_get('APPS_SCRIPT_URL'), 'headers' => ['Content-Type: application/json'], 'body' => json_encode($payload)];
    }
    public function appsscript_parse(int $http, string $body): array
    {
        $j = json_decode($body, true);
        if (is_array($j) && !empty($j['ok'])) return ['sent', null, null];
        if (is_array($j)) return ['failed', null, mb_substr('Gmail relay: ' . ($j['error'] ?? 'unknown error'), 0, 240)];
        return ['failed', null, 'The Google Apps Script did not answer correctly (HTTP ' . $http . '). Check APPS_SCRIPT_URL and that "Who has access" is set to Anyone.'];
    }

    /* =============== Resend (email) =============== */
    public function resend_request(string $to, string $subject, string $text, string $html = '', array $attachments = []): array
    {
        $from = env_get('MAIL_FROM');
        if ($from !== '' && !str_contains($from, '<')) $from = env_get('MAIL_FROM_NAME', 'HOTELGO') . ' <' . $from . '>';
        $payload = ['from' => $from, 'to' => [$to], 'subject' => $subject, 'text' => $text];
        if ($html !== '') $payload['html'] = $html;
        if ($attachments) $payload['attachments'] = $attachments;
        return ['url' => 'https://api.resend.com/emails', 'headers' => ['Authorization: Bearer ' . env_get('RESEND_API_KEY'), 'Content-Type: application/json', 'Accept: application/json'], 'body' => json_encode($payload)];
    }
    public function resend_parse(int $http, string $body): array
    {
        $j = json_decode($body, true);
        if ($http >= 200 && $http < 300 && !empty($j['id'])) return ['sent', (string) $j['id'], null];
        $msg = is_array($j) ? (($j['message'] ?? '') ?: json_encode($j)) : $body;
        return ['failed', null, mb_substr('Resend error (HTTP ' . $http . '): ' . trim($msg), 0, 240)];
    }

    public function email_enabled(): bool
    {
        return (env_get('EMAIL_PROVIDER') === 'appsscript' && env_get('APPS_SCRIPT_URL') !== '' && env_get('APPS_SCRIPT_SECRET') !== '')
            || (env_get('EMAIL_PROVIDER') === 'resend' && env_get('RESEND_API_KEY') !== '' && env_get('MAIL_FROM') !== '');
    }

    /* =============== send one message (returns the id of the saved notification) =============== */
    public function send(?int $bookingId, ?int $userId, string $channel, string $to, string $subject, string $message, array $opts = []): int
    {
        $status = 'logged'; $error = null; $ref = null; $req = null; $parse = null; $follow = false;
        try {
            if ($channel === 'sms' && env_get('SMS_PROVIDER') === 'semaphore' && env_get('SMS_API_KEY') !== '') {
                $sendText = (stripos(ltrim($message), 'test') === 0) ? 'HOTELGO: ' . $message : $message;    // Semaphore silently drops messages that start with "TEST"
                $otp = (isset($opts['otp_code']) && env_get('SMS_OTP_ROUTE', 'messages') === 'otp') ? (string) $opts['otp_code'] : null;
                $req = $this->semaphore_request($to, $sendText, $otp); $parse = 'semaphore_parse';
            } elseif ($channel === 'email' && env_get('EMAIL_PROVIDER') === 'appsscript' && env_get('APPS_SCRIPT_URL') !== '' && env_get('APPS_SCRIPT_SECRET') !== '') {
                if (!str_starts_with(env_get('APPS_SCRIPT_URL'), 'https://script.google.com/')) throw new RuntimeException('APPS_SCRIPT_URL must start with https://script.google.com/');
                $req = $this->appsscript_request($to, $subject, $message, $opts['html'] ?? '', $opts['attachments'] ?? []); $parse = 'appsscript_parse'; $follow = true;
            } elseif ($channel === 'email' && env_get('EMAIL_PROVIDER') === 'resend' && env_get('RESEND_API_KEY') !== '') {
                if (env_get('MAIL_FROM') === '') throw new RuntimeException('MAIL_FROM is not set (for testing you can use onboarding@resend.dev).');
                $req = $this->resend_request($to, $subject, $message, $opts['html'] ?? '', $opts['attachments'] ?? []); $parse = 'resend_parse';
            }
            if ($req) {
                [$http, $res, $err] = http_request('POST', $req['url'], $req['headers'], $req['body'], 25, $follow);
                if ($err !== '') {
                    $status = 'failed';
                    $error = mb_substr($err . (stripos($err, 'certificate') !== false ? ' (set CURL_CA_BUNDLE, see the guide)' : ''), 0, 240);
                } else { [$status, $ref, $error] = $this->$parse($http, $res); }
            }
        } catch (Throwable $e) { $status = 'failed'; $error = mb_substr($e->getMessage(), 0, 240); }
        return insert_row('notifications', ['booking_id' => $bookingId, 'user_id' => $userId, 'channel' => $channel, 'recipient' => $to, 'subject' => $subject, 'message' => $message,
            'status' => $status, 'error' => $error, 'provider_ref' => $ref, 'created_at' => now_str()]);
    }

    /* =============== the messages the system sends =============== */
    private function uid(array $b): ?int { return $b['user_id'] ? (int) $b['user_id'] : null; }

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
