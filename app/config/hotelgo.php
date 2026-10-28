<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
|--------------------------------------------------------------------------
| HOTELGO application settings
|--------------------------------------------------------------------------
| Loaded on every request (see config/autoload.php). Read them anywhere with
| config_item('hotelgo_...'). Values that differ per computer come from .env.
| The motel's own details (name, address, prices) live in the "settings" table.
*/

/* Booking rules */
$config['hotelgo_max_unpaid_bookings'] = max(1, (int) (getenv('MAX_UNPAID_BOOKINGS') ?: 1000));  // 1000 = no real limit (demo). Use 3 on a real site.
$config['hotelgo_roles']               = ['guest', 'staff', 'admin'];

/* School demo switches (turn both OFF on a real site) */
$config['hotelgo_demo_show_codes']     = getenv('DEMO_SHOW_CODES') === '1';
$config['hotelgo_allow_mock_payment']  = getenv('ALLOW_MOCK_PAYMENT') === '1';

/* Network */
$config['hotelgo_trust_proxy']         = getenv('TRUST_PROXY') === '1';
$config['hotelgo_app_url']             = rtrim((string) (getenv('APP_URL') ?: ''), '/');

/* Hidden staff door. The owner/staff sign-in page is NOT linked anywhere on the site.
   STAFF_LOGIN_PATH (in .env) is its secret address, e.g. STAFF_LOGIN_PATH=door-7k3m9q2x  ->  https://yoursite/door-7k3m9q2x
   Without a valid STAFF_LOGIN_PATH the staff page is OFF on a real site (only 'admin/login' on your own computer). */
$__slug = trim((string) getenv('STAFF_LOGIN_PATH'), '/');
if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9_-]{7,60}$~', $__slug) || in_array(strtolower($__slug), ['admin', 'login', 'staff', 'panel', 'api'], true)) {
    $__slug = (getenv('APP_ENV') === 'production') ? '' : 'admin/login';
}
$config['hotelgo_frontend_url']       = trim(explode(',', (string) getenv('FRONTEND_URL'))[0]);   // address of the separate React app (optional)
$config['hotelgo_staff_path']          = $__slug;
unset($__slug);

/* Sign-in protection: wrong tries allowed within the window (seconds) */
$config['hotelgo_lock_per_account']    = 5;
$config['hotelgo_lock_per_ip']         = 20;
$config['hotelgo_lock_window']         = 900;

/* One-time codes (sign-up verification and password reset) */
$config['hotelgo_otp_ttl']             = 600;   // a code is valid for 10 minutes
$config['hotelgo_otp_max_tries']       = 5;     // wrong guesses before the code dies
$config['hotelgo_otp_resend_gap']      = 30;    // seconds between two codes
$config['hotelgo_otp_per_window']      = 5;     // codes per user per window
$config['hotelgo_otp_window']          = 900;

/* "Send test" buttons on the settings page: max tests per window */
$config['hotelgo_test_limit']          = 5;
$config['hotelgo_test_window']         = 600;

/* Landing page: contact details shown on the website (change them here, not in the views) */
$config['hotelgo_contact_email']       = 'hotelgoph@gmail.com';
$config['hotelgo_contact_phone']       = '+639708655964';
$config['hotelgo_contact_location']    = 'Puerto Galera, Oriental Mindoro, Philippines';

/* Landing page map: places the guest can jump to. The motel from Settings is always the first pin.
   Add real partner motels here: ['name' => '...', 'area' => 'Sabang', 'lat' => 13.xxxx, 'lng' => 120.xxxx]. */
$config['hotelgo_places']              = [];

/* Areas of Puerto Galera shown as quick links (each opens that area on OpenStreetMap in a new tab) */
$config['hotelgo_areas']               = ['Poblacion', 'Sabang', 'White Beach', 'Talipanan', 'Aninuan', 'Balatero', 'Big La Laguna', 'Small La Laguna', 'Muelle'];
