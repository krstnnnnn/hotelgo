<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API (used by the React staff console in public/app).
|
| It is built on LavaLust's Api library: JWT access token (15 min) + refresh token (7 days, stored hashed in the
| refresh_tokens table). Every route lives under /api (see routes.php). No cookies, no CSRF token: the bearer
| token is the proof of who is calling.
|
|   public   : availability search, room types, sign in / refresh / sign out
|   staff    : everything about bookings, guests, rooms (read), dashboard, own account
|   admin    : create / edit / delete room types, rooms, staff accounts, settings, payments, notifications, reports, audit log
|
| All business rules live in the models, so this API and the PHP pages always behave the same.
*/
class ApiController extends Controller
{
    private $me = null;                 // the signed-in user (re-read from the database on every request)

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->model(['UserModel', 'LoginAttemptModel', 'RoomTypeModel', 'RoomModel', 'BookingModel', 'PaymentModel',
            'NotificationModel', 'SettingModel', 'AuditModel']);
        $this->api->rate_limit('api:' . client_ip());
    }

    /* ======================================================= helpers ======================================================= */
    private function body(): array
    {
        return $this->api->body();
    }
    private function ok($data = [], int $code = 200): void
    {
        $this->api->respond($data, $code);
    }
    private function fail(string $msg, int $code = 400): void
    {
        $this->api->respond_error($msg, $code);
    }
    private function need(string $method): void
    {
        $this->api->require_method($method);
    }
    private function who(string $min = 'staff', bool $allowTemp = false): array
    {
        if ($this->me === null) {
            $payload = $this->api->require_jwt();
            $u = $this->UserModel->session_user((int) $payload['sub']);        // also checks is_active
            if (!$u || !in_array($u['role'], ['staff', 'admin'], true)) {
                $this->fail('Unauthorized', 401);
            }
            $this->me = $u;
        }
        if ((int) $this->me['must_change_password'] === 1 && !$allowTemp) {
            $this->fail('Please change your temporary password first.', 403);
        }
        if ($min === 'admin' && $this->me['role'] !== 'admin') {
            $this->fail('Only the owner can do this.', 403);
        }
        return $this->me;
    }
    private function log(string $action, string $detail = ''): void
    {
        $this->AuditModel->record($this->me['id'] ?? null, $action, $detail);
    }
    private function ref(string $ref): array
    {
        if (!preg_match('/^HG-[A-Z0-9]{6}$/', $ref)) {
            $this->fail('Booking not found.', 404);
        }
        $b = $this->BookingModel->by_ref($ref);
        return $b ?: $this->fail('Booking not found.', 404);
    }
    private function publicUser(array $u): array
    {
        return ['id' => (int) $u['id'], 'full_name' => $u['full_name'], 'email' => $u['email'], 'mobile' => $u['mobile'],
            'role' => $u['role'], 'must_change_password' => (int) $u['must_change_password']];
    }
    private function strip(array $row, array $drop = ['password_hash']): array
    {
        return array_diff_key($row, array_flip($drop));
    }

    /* ======================================================= auth ======================================================= */
    public function login()
    {
        $this->need('POST');
        $in = $this->body();
        $email = strtolower((string) ($in['email'] ?? '')); $pass = (string) ($in['password'] ?? '');
        if ($this->LoginAttemptModel->too_many($email)) {
            $this->fail('Too many wrong attempts. Please wait 15 minutes and try again.', 429);
        }
        $u = $this->UserModel->by_email($email);
        $hash = $u['password_hash'] ?? '$2y$10$KWVj55JT.MtZUELFuZGH3OxA3815e58s7wtmIJFWHbtyqiJxN705O';   // same timing for unknown emails
        $ok = password_verify($pass, $hash) && $u && (int) $u['is_active'] === 1 && in_array($u['role'], ['staff', 'admin'], true);
        $this->LoginAttemptModel->record($email, $ok);
        if (!$ok) {
            $this->fail('Email or password is incorrect.', 401);
        }
        $this->UserModel->touch_login((int) $u['id']);
        $this->AuditModel->record((int) $u['id'], 'api_login', $u['role']);
        $tokens = $this->api->issue_tokens(['id' => (int) $u['id'], 'role' => $u['role'], 'scopes' => $u['role'] === 'admin' ? ['read', 'write', 'delete'] : ['read', 'write']]);
        $this->ok(['tokens' => $tokens, 'user' => $this->publicUser($u)]);
    }
    public function refresh()
    {
        $this->need('POST');
        $t = (string) ($this->body()['refresh_token'] ?? '');
        $this->api->refresh_access_token($t);          // answers by itself (new tokens, or an error)
    }
    public function logout()
    {
        $this->need('POST');
        $t = (string) ($this->body()['refresh_token'] ?? '');
        if ($t !== '') {
            $this->api->revoke_refresh_token($t);
        }
        $this->ok(['message' => 'Signed out.']);
    }
    public function me()
    {
        $this->need('GET');
        $this->ok(['user' => $this->publicUser($this->who('staff', true))]);
    }
    public function change_password()
    {
        $this->need('POST');
        $me = $this->who('staff', true);
        $in = $this->body();
        $u = $this->UserModel->by_id((int) $me['id']);
        $new = (string) ($in['password'] ?? '');
        if (!password_verify((string) ($in['current'] ?? ''), $u['password_hash'])) {
            $this->fail('Your current password is wrong.', 422);
        }
        if (!$this->UserModel->password_ok($new)) {
            $this->fail('New password must be 8-72 characters with letters and numbers.', 422);
        }
        $this->UserModel->set_password((int) $u['id'], $new);
        $this->log('password_changed');
        $this->ok(['message' => 'Password updated.']);
    }

    /* ======================================================= public ======================================================= */
    public function room_types()
    {
        $this->need('GET');
        $this->ok(['data' => $this->RoomTypeModel->types(true)]);
    }
    public function availability()
    {
        $this->need('GET');
        $q = $this->api->get_query_params();
        $hours = isset($q['hours']) && ctype_digit((string) $q['hours']) ? (int) $q['hours'] : 3;
        $guests = isset($q['guests']) && ctype_digit((string) $q['guests']) ? max(1, (int) $q['guests']) : 1;
        $this->BookingModel->expire_holds();
        $w = $this->BookingModel->window((string) ($q['stay'] ?? ''), (string) ($q['date'] ?? ''), (string) ($q['time'] ?? '19:00'), $hours);
        if (!$w) {
            $this->fail('Choose a valid stay type, date and start time (not in the past, within 60 days).', 422);
        }
        $avail = $this->BookingModel->availability($w['start'], $w['end']);
        $rows = [];
        foreach ($this->RoomTypeModel->types(true) as $t) {
            if ((int) $t['max_guests'] < $guests) continue;
            $t['free']  = $avail[(int) $t['id']] ?? 0;
            $t['price'] = $this->BookingModel->price($t, (string) $q['stay'], (int) $w['hours']);
            $rows[] = $t;
        }
        $this->ok(['window' => $w, 'data' => $rows]);
    }

    /* ======================================================= dashboard ======================================================= */
    public function dashboard()
    {
        $this->need('GET'); $this->who();
        $this->BookingModel->expire_holds();
        $board = $this->RoomModel->board();
        $this->ok($this->RoomModel->summary($board) + [
            'board' => $board, 'checkins_soon' => $this->BookingModel->checkins_soon(),
            'revenue_today' => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'upcoming' => $this->BookingModel->upcoming(8),
        ]);
    }

    /* ======================================================= bookings (staff) ======================================================= */
    public function bookings()                       // GET list
    {
        $this->need('GET'); $this->who();
        $q = $this->api->get_query_params();
        $status = (string) ($q['status'] ?? '');
        if (!in_array($status, array_merge([''], BookingModel::STATUSES), true)) $status = '';
        $this->BookingModel->expire_holds();
        $this->ok(['data' => $this->BookingModel->search($status, (string) ($q['q'] ?? ''))]);
    }
    public function booking($ref)                    // GET one
    {
        $this->need('GET'); $this->who();
        $b = $this->ref($ref);
        $type = $this->RoomTypeModel->type((int) $b['room_type_id']);
        $this->ok(['data' => $b,
            'free_rooms' => $this->BookingModel->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) ($type['cleaning_buffer_min'] ?? 0), (int) $b['id']),
            'payments' => $this->PaymentModel->for_booking((int) $b['id']),
            'notifications' => $this->NotificationModel->for_booking((int) $b['id'])]);
    }
    public function booking_walkin()                 // POST create (walk-in, paid in cash)
    {
        $this->need('POST'); $this->who();
        $in = $this->body();
        $type = $this->RoomTypeModel->type((int) ($in['room_type_id'] ?? 0));
        $stay = (string) ($in['stay'] ?? ''); $hours = (int) ($in['hours'] ?? 3); $guests = (int) ($in['guests'] ?? 1);
        $w = $this->BookingModel->window($stay, date('Y-m-d'), date('H:i'), $hours);
        $name = trim((string) ($in['name'] ?? '')); $mobile = normalize_mobile((string) ($in['mobile'] ?? ''));
        $err = [];
        if (!$type || !(int) $type['is_active']) $err[] = 'Choose a room type.';
        if (!$w) $err[] = 'Choose hourly (3, 6 or 12 hours) or overnight.';
        if ($type && ($guests < 1 || $guests > (int) $type['max_guests'])) $err[] = 'Too many guests for that room type.';
        if (!len_ok($name, 2, 120)) $err[] = 'Enter the guest name.';
        if (!$mobile) $err[] = 'Enter a valid mobile number.';
        if ($err) $this->fail(implode(' ', $err), 422);
        $res = $this->BookingModel->walkin($type, $w, ['name' => $name, 'mobile' => $mobile, 'email' => '', 'id_type' => '', 'id_last4' => '', 'guests' => $guests], $stay);
        if (!$res['ok']) $this->fail($res['error'], 409);
        $this->NotificationModel->booking_confirmed($res['booking']);
        $this->log('walkin_created', $res['booking']['booking_ref']);
        $this->ok(['data' => $res['booking']], 201);
    }
    public function booking_update($ref)             // PUT: fix guest details
    {
        $this->need('PUT'); $this->who();
        $b = $this->ref($ref); $in = $this->body();
        $row = [];
        if (isset($in['guest_name'])) {
            if (!len_ok(trim((string) $in['guest_name']), 2, 120)) $this->fail('Enter the guest name.', 422);
            $row['guest_name'] = trim((string) $in['guest_name']);
        }
        if (isset($in['guest_mobile'])) {
            $m = normalize_mobile((string) $in['guest_mobile']);
            if (!$m) $this->fail('Enter a valid mobile number.', 422);
            $row['guest_mobile'] = $m;
        }
        if (isset($in['guest_email'])) {
            $e = strtolower(trim((string) $in['guest_email']));
            if ($e !== '' && !filter_var($e, FILTER_VALIDATE_EMAIL)) $this->fail('Enter a valid email address.', 422);
            $row['guest_email'] = $e;
        }
        if (isset($in['guests'])) {
            $type = $this->RoomTypeModel->type((int) $b['room_type_id']);
            $g = (int) $in['guests'];
            if ($g < 1 || $g > (int) ($type['max_guests'] ?? 1)) $this->fail('Number of guests is not allowed for this room type.', 422);
            $row['guests'] = $g;
        }
        if (!$row) $this->fail('Nothing to change.', 422);
        $this->BookingModel->update_guest((int) $b['id'], $row);
        $this->log('booking_updated', $ref);
        $this->ok(['data' => $this->BookingModel->by_ref($ref)]);
    }
    public function booking_action($ref)             // POST: checkin | checkout | cancel | resend | reassign
    {
        $this->need('POST'); $this->who();
        $b = $this->ref($ref); $in = $this->body();
        $do = (string) ($in['do'] ?? '');
        if (!in_array($do, ['checkin', 'checkout', 'cancel', 'resend', 'reassign'], true)) $this->fail('Unknown action.', 422);
        [$level, $message] = $this->BookingModel->staff_action($b, $do, (int) ($in['room_id'] ?? 0));
        $this->log('reservation_' . $do, $ref);
        $this->ok(['level' => $level, 'message' => $message, 'data' => $this->BookingModel->by_ref($ref)], $level === 'bad' ? 409 : 200);
    }
    public function booking_delete($ref)             // DELETE (only cancelled / expired bookings with no payment)
    {
        $this->need('DELETE'); $this->who('admin');
        $this->ref($ref);
        [$ok, $why] = $this->BookingModel->remove($ref);
        if (!$ok) $this->fail($why, 409);
        $this->log('booking_deleted', $ref);
        $this->ok(['message' => $why]);
    }

    /* ======================================================= guests ======================================================= */
    public function guests()
    {
        $this->need('GET'); $this->who();
        $q = $this->api->get_query_params();
        $this->ok(['data' => $this->UserModel->guests((string) ($q['q'] ?? ''))]);
    }
    public function guest($id)
    {
        $this->need('GET'); $this->who();
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        $this->log('view_guest', 'user #' . $g['id']);
        $this->ok(['data' => $g, 'bookings' => $this->BookingModel->for_user((int) $g['id'])]);
    }
    public function guest_update($id)                // PUT: name / email / mobile / staff note
    {
        $this->need('PUT'); $this->who();
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        $in = $this->body(); $row = [];
        if (isset($in['full_name'])) {
            if (!len_ok(trim((string) $in['full_name']), 2, 120)) $this->fail('Enter the name.', 422);
            $row['full_name'] = trim((string) $in['full_name']);
        }
        if (isset($in['email'])) {
            $e = strtolower(trim((string) $in['email']));
            $other = $this->UserModel->by_email($e);
            if (!filter_var($e, FILTER_VALIDATE_EMAIL) || ($other && (int) $other['id'] !== (int) $g['id'])) $this->fail('Email is invalid or already used.', 422);
            $row['email'] = $e;
        }
        if (isset($in['mobile'])) {
            $m = normalize_mobile((string) $in['mobile']);
            $other = $m ? $this->UserModel->by_mobile($m) : null;
            if (!$m || ($other && (int) $other['id'] !== (int) $g['id'])) $this->fail('Mobile number is invalid or already used.', 422);
            $row['mobile'] = $m;
        }
        if ($row) $this->UserModel->save_profile((int) $g['id'], $row);
        if (isset($in['staff_note'])) $this->UserModel->save_note((int) $g['id'], (string) $in['staff_note']);
        $this->log('guest_updated', 'user #' . $g['id']);
        $this->ok(['data' => $this->UserModel->guest((int) $g['id'])]);
    }
    public function guest_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        $g = $this->UserModel->guest((int) $id) ?: $this->fail('Guest not found.', 404);
        [$ok, $why] = $this->UserModel->remove((int) $g['id']);
        if (!$ok) $this->fail($why, 409);
        $this->log('guest_deleted', 'user #' . $g['id']);
        $this->ok(['message' => $why]);
    }

    /* ======================================================= room types ======================================================= */
    public function types()                          // GET all (including hidden ones)
    {
        $this->need('GET'); $this->who();
        $this->ok(['data' => $this->RoomTypeModel->types(false)]);
    }
    public function type_create()
    {
        $this->need('POST'); $this->who('admin');
        $d = $this->RoomTypeModel->clean($this->body()) ?: $this->fail('Please check the name, guests and prices (numbers only).', 422);
        $id = $this->RoomTypeModel->save(null, $d);
        $this->log('room_type_created', $d['name']);
        $this->ok(['data' => $this->RoomTypeModel->type($id)], 201);
    }
    public function type_update($id)
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->RoomTypeModel->type((int) $id) ?: $this->fail('Room type not found.', 404);
        $d = $this->RoomTypeModel->clean($this->body() + $cur) ?: $this->fail('Please check the name, guests and prices (numbers only).', 422);
        $this->RoomTypeModel->save((int) $id, $d);
        $this->log('room_type_updated', $d['name']);
        $this->ok(['data' => $this->RoomTypeModel->type((int) $id)]);
    }
    public function type_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        [$ok, $why] = $this->RoomTypeModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('room_type_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }

    /* ======================================================= rooms ======================================================= */
    public function rooms()
    {
        $this->need('GET'); $this->who();
        $this->ok(['data' => $this->RoomModel->rooms()]);
    }
    public function room_create()
    {
        $this->need('POST'); $this->who('admin');
        [$d, $why] = $this->RoomModel->clean_room($this->body());
        if (!$d) $this->fail($why, 422);
        $id = $this->RoomModel->add_room($d['room_no'], $d['room_type_id'], $d['floor']);
        $this->RoomModel->save_room($id, ['status' => $d['status']]);
        $this->log('room_added', $d['room_no']);
        $this->ok(['data' => $this->RoomModel->room($id)], 201);
    }
    public function room_update($id)
    {
        $this->need('PUT'); $this->who('admin');
        $cur = $this->RoomModel->room((int) $id) ?: $this->fail('Room not found.', 404);
        [$d, $why] = $this->RoomModel->clean_room($this->body() + $cur, (int) $id);
        if (!$d) $this->fail($why, 422);
        $this->RoomModel->save_room((int) $id, $d);
        $this->log('room_updated', $d['room_no']);
        $this->ok(['data' => $this->RoomModel->room((int) $id)]);
    }
    public function room_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        [$ok, $why] = $this->RoomModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('room_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }

    /* ======================================================= payments (owner) ======================================================= */
    public function payments()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->PaymentModel->recent(200), 'paid_today' => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'refunded' => $this->PaymentModel->total('refunded'), 'waiting' => $this->PaymentModel->waiting_count()]);
    }
    public function payment_refund($id)
    {
        $this->need('POST'); $this->who('admin');
        [$ok, $msg] = $this->PaymentModel->refund((int) $id);
        $this->log($ok ? 'refund' : 'refund_failed', 'payment #' . (int) $id);
        $this->ok(['message' => $ok ? 'Payment refunded and the booking was cancelled.' : $msg], $ok ? 200 : 409);
    }

    /* ======================================================= notifications (owner) ======================================================= */
    public function notifications()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->NotificationModel->recent(200)]);
    }
    public function notification_retry($id)
    {
        $this->need('POST'); $this->who('admin');
        $ok = $this->NotificationModel->retry((int) $id);
        $this->ok(['message' => $ok ? 'Sent again.' : 'Could not send again.'], $ok ? 200 : 409);
    }
    public function notification_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        if (!$this->NotificationModel->remove((int) $id)) $this->fail('Notification not found.', 404);
        $this->ok(['message' => 'Notification deleted.']);
    }

    /* ======================================================= staff accounts (owner) ======================================================= */
    public function staff()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->UserModel->staff_list()]);
    }
    public function staff_create()
    {
        $this->need('POST'); $this->who('admin');
        $in = $this->body();
        [$ok, $msg, $temp, $id] = $this->UserModel->create_staff((string) ($in['full_name'] ?? ''), (string) ($in['email'] ?? ''), (string) ($in['mobile'] ?? ''));
        if (!$ok) $this->fail($msg, 422);
        $this->log('staff_created', strtolower((string) $in['email']));
        $this->ok(['message' => 'Staff account created. The temporary password is shown only once.', 'temporary_password' => $temp, 'id' => $id], 201);
    }
    public function staff_update($id)                // PUT: name / mobile, or is_active
    {
        $this->need('PUT'); $me = $this->who('admin');
        $u = $this->UserModel->by_id((int) $id);
        if (!$u || $u['role'] !== 'staff') $this->fail('Staff account not found.', 404);
        $in = $this->body(); $row = [];
        if (isset($in['full_name'])) {
            if (!len_ok(trim((string) $in['full_name']), 2, 120)) $this->fail('Enter the name.', 422);
            $row['full_name'] = trim((string) $in['full_name']);
        }
        if (isset($in['mobile'])) {
            $m = normalize_mobile((string) $in['mobile']);
            $other = $m ? $this->UserModel->by_mobile($m) : null;
            if (!$m || ($other && (int) $other['id'] !== (int) $u['id'])) $this->fail('Mobile number is invalid or already used.', 422);
            $row['mobile'] = $m;
        }
        if ($row) $this->UserModel->save_profile((int) $u['id'], $row);
        if (isset($in['is_active']) && (int) (bool) $in['is_active'] !== (int) $u['is_active']) $this->UserModel->toggle_staff((int) $u['id']);
        $this->log('staff_updated', '#' . (int) $id);
        $this->ok(['data' => $this->strip($this->UserModel->by_id((int) $id))]);
    }
    public function staff_delete($id)
    {
        $this->need('DELETE'); $this->who('admin');
        $u = $this->UserModel->by_id((int) $id);
        if (!$u || $u['role'] !== 'staff') $this->fail('Staff account not found.', 404);
        [$ok, $why] = $this->UserModel->remove((int) $id);
        if (!$ok) $this->fail($why, 409);
        $this->log('staff_deleted', '#' . (int) $id);
        $this->ok(['message' => $why]);
    }

    /* ======================================================= settings, reports, audit (owner) ======================================================= */
    public function settings()
    {
        $this->need('GET'); $this->who('admin');
        $this->call->library('integrations');
        $all = $this->SettingModel->all_values();
        $this->ok(['data' => array_intersect_key($all, SettingModel::RULES), 'integrations' => $this->integrations->status()]);
    }
    public function settings_save()
    {
        $this->need('PUT'); $this->who('admin');
        $err = $this->SettingModel->save_all($this->body());
        if ($err) $this->fail($err, 422);
        $this->log('settings_saved');
        $this->ok(['data' => array_intersect_key($this->SettingModel->all_values(), SettingModel::RULES)]);
    }
    public function reports()
    {
        $this->need('GET'); $this->who('admin');
        $q = $this->api->get_query_params();
        $ok = fn($d) => is_string($d) && ($x = DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $to = $ok($q['to'] ?? null) ? $q['to'] : date('Y-m-d'); $from = $ok($q['from'] ?? null) ? $q['from'] : date('Y-m-d', strtotime('-6 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        if (strtotime($to) - strtotime($from) > 366 * 86400) $this->fail('Please choose at most one year.', 422);
        $this->ok($this->PaymentModel->report($from, $to));
    }
    public function audit()
    {
        $this->need('GET'); $this->who('admin');
        $this->ok(['data' => $this->AuditModel->recent(300)]);
    }
}
