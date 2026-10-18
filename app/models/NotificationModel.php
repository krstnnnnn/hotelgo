<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* SMS + Email. If no provider key is set, the message is only saved ("logged") so you can still demo it. */
class NotificationModel extends Model
{
    protected $table = 'notifications';

    private function http(string $url, array $headers, string $body): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $res = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
        return [$code, (string) $res, $err];
    }

    public function send(?int $bookingId, ?int $userId, string $channel, string $to, string $subject, string $message): void
    {
        $status = 'logged'; $error = null; $ref = null;
        try {
            if ($channel === 'sms' && env_get('SMS_PROVIDER') === 'semaphore' && env_get('SMS_API_KEY') !== '') {
                [$code, $res, $err] = $this->http('https://api.semaphore.co/api/v4/messages', ['Content-Type: application/x-www-form-urlencoded'],
                    http_build_query(['apikey' => env_get('SMS_API_KEY'), 'number' => $to, 'message' => $message, 'sendername' => env_get('SMS_SENDER', 'HOTELGO')]));
                $status = ($code >= 200 && $code < 300) ? 'sent' : 'failed'; $error = $status === 'failed' ? mb_substr($err ?: $res, 0, 200) : null;
            } elseif ($channel === 'email' && env_get('EMAIL_PROVIDER') === 'resend' && env_get('RESEND_API_KEY') !== '') {
                [$code, $res, $err] = $this->http('https://api.resend.com/emails', ['Authorization: Bearer ' . env_get('RESEND_API_KEY'), 'Content-Type: application/json'],
                    json_encode(['from' => env_get('MAIL_FROM'), 'to' => [$to], 'subject' => $subject, 'text' => $message]));
                $status = ($code >= 200 && $code < 300) ? 'sent' : 'failed'; $error = $status === 'failed' ? mb_substr($err ?: $res, 0, 200) : null;
            }
        } catch (Throwable $e) { $status = 'failed'; $error = mb_substr($e->getMessage(), 0, 200); }
        insert_row('notifications', ['booking_id' => $bookingId, 'user_id' => $userId, 'channel' => $channel, 'recipient' => $to, 'subject' => $subject, 'message' => $message, 'status' => $status, 'error' => $error, 'provider_ref' => $ref, 'created_at' => now_str()]);
    }

    public function booking_confirmed(array $b): void
    {
        $sms = 'HOTELGO: Booking ' . $b['booking_ref'] . ' confirmed. Room ' . $b['room_no'] . ', ' . fmt_dt($b['start_at'], 'D j M g:i A') . ' - ' . fmt_dt($b['end_at'], 'g:i A') . '. Show this ref at check-in.';
        $mail = "Hi " . $b['guest_name'] . ",\n\nYour HOTELGO booking is confirmed.\nReference: " . $b['booking_ref'] . "\nRoom: " . $b['room_no'] . ' (' . $b['type_name'] . ")\nCheck-in: " . fmt_dt($b['start_at']) . "\nCheck-out: " . fmt_dt($b['end_at']) . "\nPaid: " . money($b['total']) . "\n\n" . setting('motel_name') . ', ' . setting('motel_address') . "\nDirections: " . maps_link() . "\n";
        $this->send((int) $b['id'], $b['user_id'] ? (int) $b['user_id'] : null, 'sms', $b['guest_mobile'], '', $sms);
        if ($b['guest_email'] !== '') $this->send((int) $b['id'], $b['user_id'] ? (int) $b['user_id'] : null, 'email', $b['guest_email'], 'Your HOTELGO booking is confirmed', $mail);
    }
    public function booking_cancelled(array $b): void
    {
        $this->send((int) $b['id'], $b['user_id'] ? (int) $b['user_id'] : null, 'sms', $b['guest_mobile'], '', 'HOTELGO: Booking ' . $b['booking_ref'] . ' was cancelled. Any payment will be refunded to your original payment method.');
    }
    public function booking_rescheduled(array $b): void
    {
        $this->send((int) $b['id'], $b['user_id'] ? (int) $b['user_id'] : null, 'sms', $b['guest_mobile'], '', 'HOTELGO: Booking ' . $b['booking_ref'] . ' moved to ' . fmt_dt($b['start_at'], 'D j M g:i A') . ' - ' . fmt_dt($b['end_at'], 'g:i A') . ', Room ' . $b['room_no'] . '.');
    }
    public function code(int $userId, string $mobile, string $purpose, string $code): void
    {
        $this->send(null, $userId, 'sms', $mobile, '', 'HOTELGO: your ' . ($purpose === 'reset' ? 'password reset' : 'verification') . ' code is ' . $code . '. It expires in 10 minutes. Never share this code.');
    }
}
