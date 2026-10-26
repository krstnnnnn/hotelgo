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
function staff_login_path(): string { return (string) config_item('hotelgo_staff_path'); }
function is_production(): bool { return config_item('environment') === 'production'; }
function money($n): string { return Money::format($n); }
function now_str(): string { return date('Y-m-d H:i:s'); }
function fmt_dt($s, string $f = 'D, j M Y · g:i A'): string { $t = strtotime((string) $s); return $t ? date($f, $t) : ''; }
function post_str(string $k, string $d = ''): string { $v = $_POST[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function get_str(string $k, string $d = ''): string { $v = $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
function post_int(string $k, int $d = 0): int { $v = $_POST[$k] ?? ''; return (is_string($v) && preg_match('/^\d{1,9}$/', $v)) ? (int) $v : $d; }
function get_int(string $k, int $d = 0): int { $v = $_GET[$k] ?? ''; return (is_string($v) && preg_match('/^\d{1,9}$/', $v)) ? (int) $v : $d; }
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

/* ---------- flash messages ---------- */
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }

/* ---------- redirect that ALWAYS stops the script ---------- */
function go(string $path = '', int $code = 302): void {
    header('Location: ' . site_url($path), true, $code);
    exit;
}

/* ---------- CSRF (our own check, see CsrfMiddleware) ---------- */
function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_input(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }

/* ---------- who is logged in? (always re-read from the database) ---------- */
function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    $id = $_SESSION['uid'] ?? null;
    if (!$id) return $user = null;
    $row = lava_instance()->call->model('UserModel')->session_user((int) $id);
    if (!$row) { unset($_SESSION['uid']); return $user = null; }
    return $user = $row;
}
function has_role(string ...$roles): bool { $u = current_user(); return $u !== null && in_array($u['role'], $roles, true); }
function login_user(int $id): void {
    session_regenerate_id(true);               // stops session fixation
    $_SESSION['uid'] = $id;
    $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    lava_instance()->call->model('UserModel')->touch_login($id);
}
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/* ---------- audit log ---------- */
function audit(string $action, string $detail = ''): void {
    $u = current_user();
    lava_instance()->call->model('AuditModel')->record($u['id'] ?? null, $action, $detail);
}

/* ---------- pages ---------- */
function render(string $view, array $data = [], string $layout = 'site'): void {
    $L = lava_instance();
    $data['_user'] = current_user();
    $data['_flash'] = flashes();
    $data['_title'] = $data['title'] ?? 'HOTELGO';
    if ($data['_user']) { header('Cache-Control: no-store, max-age=0'); header('Pragma: no-cache'); }
    $L->call->view($layout === 'admin' ? 'layout/admin_header' : 'layout/header', $data);
    $L->call->view($view, $data);
    $L->call->view($layout === 'admin' ? 'layout/admin_footer' : 'layout/footer', $data);
}
function render_admin(string $view, array $data = []): void { render($view, $data, 'admin'); }
function abort(int $code = 404, string $message = 'That page does not exist.'): void {
    http_response_code($code);
    render('errors/http', ['code' => $code, 'message' => $message, 'title' => 'Error ' . $code]);
    exit;
}

/* ---------- formatting / small business helpers ---------- */
function booking_ref(): string {
    $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $abc[random_int(0, strlen($abc) - 1)];
    return 'HG-' . $s;
}
function normalize_mobile(string $m): ?string { return Phone::normalize($m); }
function mask_mobile(?string $m): string { return Phone::mask($m); }
function mask_email(?string $m): string { return Phone::maskEmail($m); }
function status_class(string $s): string {
    return match ($s) { 'confirmed', 'paid', 'delivered', 'sent', 'active' => 'ok', 'pending_payment', 'pending', 'logged', 'maintenance', 'refund_pending' => 'warn', 'checked_in' => 'info', 'failed' => 'bad', default => 'gray' };
}
function stay_label(array $b): string { return $b['stay_type'] === 'overnight' ? 'Overnight' : 'Hourly · ' . (int) $b['hours'] . ' hrs'; }
function csv_safe($v): string { return Csv::safe($v); }   // stops CSV formula injection
/* The hotel of a booking (its name, address, map position ...). Falls back to the old single-motel settings. */
function hotel_of(?array $b, string $f): string {
    $v = $b['hotel_' . $f] ?? null;
    return ($v !== null && $v !== '') ? (string) $v : setting('motel_' . $f, $f === 'lat' ? '13.5188' : ($f === 'lng' ? '120.9647' : ''));
}
function maps_link(?array $b = null): string { return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(hotel_of($b, 'lat') . ',' . hotel_of($b, 'lng')); }   // opens turn-by-turn directions

function nav_active(string $path): string {
    $cur = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
    $cur = preg_replace('#^.*?(admin|rooms|my-bookings|booking|login|register)#', '$1', $cur) ?: $cur;
    return ($cur === $path || ($path !== 'admin' && str_starts_with($cur, $path))) ? ' active' : '';
}

/* The search form is used on the home page AND the results page */
function search_form(array $p): string {
    $stay = $p['stay'] ?? 'hourly'; $times = '';
    for ($m = 0; $m < 1440; $m += 30) { $t = sprintf('%02d:%02d', intdiv($m, 60), $m % 60); $times .= '<option value="' . $t . '"' . ($t === ($p['time'] ?? '19:00') ? ' selected' : '') . '>' . date('g:i A', strtotime($t)) . '</option>'; }
    $hrs = ''; foreach ([3, 6, 12] as $h) $hrs .= '<option value="' . $h . '"' . ((int) ($p['hours'] ?? 3) === $h ? ' selected' : '') . '>' . $h . ' hours</option>';
    $areaOpts = ''; foreach (lava_instance()->call->model('HotelModel')->areas() as $a) $areaOpts .= '<option' . (($p['area'] ?? '') === $a ? ' selected' : '') . '>' . e($a) . '</option>';
    $g = ''; for ($i = 1; $i <= 10; $i++) $g .= '<option value="' . $i . '"' . ((int) ($p['guests'] ?? 2) === $i ? ' selected' : '') . '>' . $i . ' guest' . ($i > 1 ? 's' : '') . '</option>';
    return '<form method="get" action="' . e(site_url('rooms')) . '"><div class="seg">'
        . '<label><input type="radio" name="stay" value="hourly"' . ($stay === 'hourly' ? ' checked' : '') . '> Hourly</label>'
        . '<label><input type="radio" name="stay" value="overnight"' . ($stay === 'overnight' ? ' checked' : '') . '> Overnight</label></div>'
        . '<div class="sf"><div><label for="date">Date</label><input type="date" id="date" name="date" required min="' . date('Y-m-d') . '" max="' . date('Y-m-d', strtotime('+60 days')) . '" value="' . e($p['date'] ?? date('Y-m-d')) . '"></div>'
        . '<div data-hourly-only><label for="time">Start time</label><select id="time" name="time">' . $times . '</select></div>'
        . '<div data-hourly-only><label for="hours">Duration</label><select id="hours" name="hours">' . $hrs . '</select></div>'
        . '<div><label for="guests">Guests</label><select id="guests" name="guests">' . $g . '</select></div>'
        . '<div><label for="area">Area</label><select id="area" name="area"><option value="">All of Puerto Galera</option>' . $areaOpts . '</select></div><button class="btn btn-amber" type="submit">Check availability</button></div></form>';
}

/* ---------- absolute address (used inside the QR code) ---------- */
function abs_url(string $path = ''): string {
    $base = (string) config_item('hotelgo_app_url');
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (config_item('hotelgo_trust_proxy') && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $base . '/' . ltrim($path, '/');
}

/* ---------- QR code as an inline SVG picture (library app/libraries/Qr.php) ---------- */
function qr_svg(string $text): string { return lava_instance()->call->library('qr')->svg($text); }

/* ---------- small icon set (inline SVG) ---------- */
function icon(string $name, string $color = 'currentColor', int $size = 20): string {
    $p = [
        'wallet' => '<path d="M3 7a3 3 0 0 1 3-3h11v3"/><rect x="3" y="7" width="18" height="13" rx="3"/><path d="M16 13.5h2"/>',
        'card' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18M7 15h4"/>',
        'bank' => '<path d="M3 10 12 4l9 6M5 10v8M9.5 10v8M14.5 10v8M19 10v8M3 20h18"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="3"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'bell' => '<path d="M6 17V11a6 6 0 0 1 12 0v6l2 2H4z"/><path d="M10 21h4"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'message' => '<path d="M4 5h16v11H9l-5 4z"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'check' => '<path d="m5 12 5 5 9-10"/>',
    ][$name] ?? '';
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . e($color) . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
function time_options(string $selected = '19:00'): string {
    $o = '';
    for ($m = 0; $m < 1440; $m += 30) { $t = sprintf('%02d:%02d', intdiv($m, 60), $m % 60); $o .= '<option value="' . $t . '"' . ($t === $selected ? ' selected' : '') . '>' . date('g:i A', strtotime($t)) . '</option>'; }
    return $o;
}
function pay_label(string $m): string { return ['ewallet' => 'E-wallet', 'card' => 'Card', 'bank' => 'Bank / OTC', 'cash' => 'Cash'][$m] ?? 'Online'; }
function notice_label(string $s): string { return ['sent' => 'Sent', 'logged' => 'Demo', 'failed' => 'Failed', 'retried' => 'Retried'][$s] ?? $s; }

/* Semaphore wants 09XXXXXXXXX (we store +639XXXXXXXXX) */
function to_local_mobile(string $m): string { return Phone::toLocal($m); }

/* The calendar file (.ics): downloaded on the ticket page AND attached to the confirmation email */
function ics_content(array $b): string { return Ics::booking($b, hotel_of($b, 'name'), hotel_of($b, 'address')); }

/* Free map (OpenStreetMap) used when there is no Google Maps key */
function osm_embed_url(?array $b = null): string {
    $lat = (float) hotel_of($b, 'lat'); $lng = (float) hotel_of($b, 'lng'); $d = 0.004;
    return 'https://www.openstreetmap.org/export/embed.html?bbox=' . rawurlencode(($lng - $d) . ',' . ($lat - $d) . ',' . ($lng + $d) . ',' . ($lat + $d)) . '&layer=mapnik&marker=' . rawurlencode($lat . ',' . $lng);
}
