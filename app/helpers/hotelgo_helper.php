<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------
| HOTELGO helper functions (loaded automatically, see config/autoload.php)
|--------------------------------------------------------------------
| Rule 1: every value shown on a page goes through e()      -> stops XSS
| Rule 2: database access lives in app/models (query builder, bound values) -> stops SQL injection
| Rule 3: every redirect uses go() (it always stops the script)
| Settings come from config_item() (app/config/hotelgo.php and integrations.php).
| The time zone is set by LavaLust itself ($config['date_default_timezone']).
*/

use Hotelgo\Support\Csv;
use Hotelgo\Support\Ics;
use Hotelgo\Support\Money;
use Hotelgo\Support\Phone;

/* ---------- small basics ---------- */
function e($value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
/* Stops a request with a JSON error (used by MigrationController) */
function abort(int $code = 404, string $message = 'Not found.'): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode(['error' => $message]); exit; }
function is_production(): bool { return config_item('environment') === 'production'; }

function money($n): string { return Money::format($n); }
function now_str(): string { return date('Y-m-d H:i:s'); }
function fmt_dt($s, string $f = 'D, j M Y · g:i A'): string { $t = strtotime((string) $s); return $t ? date($f, $t) : ''; }
function len_ok(string $s, int $min, int $max): bool { $l = mb_strlen($s); return $l >= $min && $l <= $max; }

function client_ip(): string {
    if (config_item('hotelgo_trust_proxy') && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* ---------- motel settings (the "settings" table, cached per request) ---------- */
function setting(string $key, string $default = ''): string { return lava_instance()->call->model('SettingModel')->value($key, $default); }

/* ---------- formatting / small business helpers ---------- */
function booking_ref(): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return 'HG-' . $s;
}
function normalize_mobile(string $m): ?string { return Phone::normalize($m); }
function mask_mobile(?string $m): string { return Phone::mask($m); }
function mask_email(?string $m): string { return Phone::maskEmail($m); }
function stay_label(array $b): string { return $b['stay_type'] === 'overnight' ? 'Overnight' : 'Hourly · ' . (int) $b['hours'] . ' hrs'; }
/* The hotel of a booking (its name, address, map position ...). Falls back to the old single-motel settings. */
function hotel_of(?array $b, string $f): string {
    $v = $b['hotel_' . $f] ?? null;
    return ($v !== null && $v !== '') ? (string) $v : setting('motel_' . $f, $f === 'lat' ? '13.5188' : ($f === 'lng' ? '120.9647' : ''));
}
function maps_link(?array $b = null): string { return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(hotel_of($b, 'lat') . ',' . hotel_of($b, 'lng')); }   // opens turn-by-turn directions

/* ---------- absolute address (used inside the QR code) ---------- */
function abs_url(string $path = ''): string {
    $base = (string) config_item('hotelgo_app_url');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (config_item('hotelgo_trust_proxy') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $base . '/' . ltrim($path, '/');
}

/* An address inside the React website (hotelgo-web), e.g. front_url('booking/HG-ABC123') -> https://site/#/booking/HG-ABC123.  Used in messages and as the payment return address. */
function front_url(string $path = ''): string
{
    $base = rtrim((string) config_item('hotelgo_frontend_url'), '/');
    if ($base === '') $base = rtrim(abs_url(''), '/');            // no React address set: fall back to this server (local tests)
    return $base . '/#/' . ltrim($path, '/');
}

/* ---------- QR code as an inline SVG picture (library app/libraries/Qr.php) ---------- */
function qr_svg(string $text): string { return lava_instance()->call->library('qr')->svg($text); }

/* ---------- VIP rooms: the services a room type includes, as [title, details] pairs ("Title: details" per line) ---------- */
function included_list(array $t): array {
    $out = [];
    foreach (preg_split('/\R/', (string) ($t['included_services'] ?? '')) as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $out[] = str_contains($l, ':') ? array_map('trim', explode(':', $l, 2)) : [$l, ''];
    }
    return $out;
}

function pay_label(string $m): string { return ['ewallet' => 'E-wallet', 'card' => 'Card', 'bank' => 'Bank / OTC', 'cash' => 'Cash'][$m] ?? 'Online'; }
function notice_label(string $s): string { return ['sent' => 'Sent', 'logged' => 'Not sent', 'failed' => 'Failed', 'retried' => 'Retried'][$s] ?? $s; }

/* Semaphore wants 09XXXXXXXXX (we store +639XXXXXXXXX) */
function to_local_mobile(string $m): string { return Phone::toLocal($m); }

/* The calendar file (.ics): downloaded on the ticket page AND attached to the confirmation email */
function ics_content(array $b): string { return Ics::booking($b, hotel_of($b, 'name'), hotel_of($b, 'address')); }

