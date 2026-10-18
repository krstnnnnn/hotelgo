<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Owner-only area (AdminMiddleware in routes.php): rooms & rates, payments, notifications, reports, staff, audit, settings. */
class ManageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        foreach (['RoomModel', 'BookingModel', 'PaymentModel', 'NotificationModel', 'UserModel'] as $m) $this->call->model($m);
    }

    /* ---------------- rooms & rates ---------------- */
    public function rooms()
    {
        $edit = get_int('edit') ? $this->RoomModel->type(get_int('edit')) : null;
        render_admin('admin/rooms', ['types' => $this->RoomModel->types(false), 'rooms' => $this->RoomModel->rooms(), 'edit' => $edit, 'title' => 'Rooms & rates']);
    }
    public function save_type()
    {
        $id = post_int('id'); $d = ['name' => post_str('name'), 'description' => mb_substr(post_str('description'), 0, 500), 'amenities' => mb_substr(post_str('amenities'), 0, 250), 'max_guests' => post_int('max_guests', 2), 'cleaning_buffer_min' => post_int('cleaning_buffer_min', 15), 'is_active' => post_str('is_active') === '1' ? 1 : 0];
        foreach (['price_3h', 'price_6h', 'price_12h', 'price_overnight'] as $k) $d[$k] = filter_var(post_str($k), FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
        $bad = !len_ok($d['name'], 2, 80) || $d['max_guests'] < 1 || $d['max_guests'] > 20 || $d['cleaning_buffer_min'] > 180 || in_array(false, [$d['price_3h'], $d['price_6h'], $d['price_12h'], $d['price_overnight']], true);
        if ($bad) { flash('bad', 'Please check the name, guests and prices (numbers only).'); go('admin/rooms' . ($id ? '?edit=' . $id : '')); }
        $this->RoomModel->save_type($id ?: null, $d);
        audit($id ? 'room_type_updated' : 'room_type_created', $d['name']);
        flash('ok', 'Room type saved.');
        go('admin/rooms');
    }
    public function add_room()
    {
        $no = post_str('room_no'); $type = post_int('type');
        if (!preg_match('/^[A-Za-z0-9\-]{1,10}$/', $no) || !$this->RoomModel->type($type) || q_val('SELECT COUNT(*) FROM rooms WHERE room_no = ?', [$no]) > 0) { flash('bad', 'Room number is invalid or already exists.'); go('admin/rooms'); }
        $this->RoomModel->add_room($no, $type, max(1, min(50, post_int('floor', 1))));
        audit('room_added', $no); flash('ok', 'Room added.'); go('admin/rooms');
    }
    public function room_status($id)
    {
        $s = post_str('status');
        if (!in_array($s, ['active', 'maintenance'], true)) abort(400, 'Invalid status.');
        $this->RoomModel->set_room_status((int) $id, $s); audit('room_status', '#' . (int) $id . ' ' . $s);
        flash('ok', 'Room updated.'); go('admin/rooms');
    }

    /* ---------------- payments ---------------- */
    public function payments()
    {
        $rows = q_all('SELECT p.*, b.booking_ref, b.guest_name FROM payments p JOIN bookings b ON b.id = p.booking_id ORDER BY p.id DESC LIMIT 200');
        $today = date('Y-m-d 00:00:00');
        $sum = fn(string $st) => (float) q_val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = ?' . ($st === 'paid' ? ' AND paid_at >= ?' : ''), $st === 'paid' ? [$st, $today] : [$st]);
        render_admin('admin/payments', ['rows' => $rows, 'paid_today' => $sum('paid'), 'refunded' => $sum('refunded'), 'pending' => (int) q_val("SELECT COUNT(*) FROM bookings WHERE status = 'pending_payment'"), 'title' => 'Payments']);
    }
    public function refund($id)
    {
        if ($this->PaymentModel->refund((int) $id)) { audit('refund', 'payment #' . (int) $id); flash('ok', 'Payment marked as refunded and the booking was cancelled.'); }
        else flash('bad', 'That payment cannot be refunded.');
        go('admin/payments');
    }

    /* ---------------- notifications ---------------- */
    public function notifications()
    {
        render_admin('admin/notifications', ['rows' => q_all('SELECT * FROM notifications ORDER BY id DESC LIMIT 200'), 'title' => 'Notifications']);
    }
    public function notification_retry($id)
    {
        $n = q_one("SELECT * FROM notifications WHERE id = ? AND status = 'failed'", [(int) $id]);
        if ($n) { $this->NotificationModel->send($n['booking_id'] ? (int) $n['booking_id'] : null, $n['user_id'] ? (int) $n['user_id'] : null, $n['channel'], $n['recipient'], (string) $n['subject'], $n['message']); q("UPDATE notifications SET status = 'retried' WHERE id = ?", [$n['id']]); flash('ok', 'Sent again.'); }
        go('admin/notifications');
    }

    /* ---------------- reports ---------------- */
    private function range(): array
    {
        $ok = fn($d) => ($x = DateTime::createFromFormat('!Y-m-d', $d)) && $x->format('Y-m-d') === $d;
        $to = $ok(get_str('to')) ? get_str('to') : date('Y-m-d'); $from = $ok(get_str('from')) ? get_str('from') : date('Y-m-d', strtotime('-6 days'));
        if ($from > $to) [$from, $to] = [$to, $from];
        return [$from, $to];
    }
    public function reports()
    {
        [$from, $to] = $this->range();
        $rows = q_all("SELECT p.amount, p.paid_at, b.stay_type, b.hours, b.start_at, t.name AS type_name FROM payments p JOIN bookings b ON b.id = p.booking_id JOIN room_types t ON t.id = b.room_type_id WHERE p.status = 'paid' AND p.paid_at >= ? AND p.paid_at < ?", [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']);
        $byDay = []; $byStay = ['hourly' => 0, 'overnight' => 0]; $byType = []; $hours = 0; $hourly = 0; $heat = [];
        for ($d = strtotime($from); $d <= strtotime($to); $d += 86400) $byDay[date('Y-m-d', $d)] = ['hourly' => 0, 'overnight' => 0];
        foreach ($rows as $r) {
            $day = date('Y-m-d', strtotime($r['paid_at']));
            if (isset($byDay[$day])) $byDay[$day][$r['stay_type']] += (float) $r['amount'];
            $byStay[$r['stay_type']] += (float) $r['amount']; $byType[$r['type_name']] = ($byType[$r['type_name']] ?? 0) + (float) $r['amount'];
            if ($r['stay_type'] === 'hourly') { $hours += (int) $r['hours']; $hourly++; }
            $k = date('w', strtotime($r['start_at'])) . '-' . date('G', strtotime($r['start_at'])); $heat[$k] = ($heat[$k] ?? 0) + 1;
        }
        render_admin('admin/reports', ['from' => $from, 'to' => $to, 'total' => array_sum(array_column($rows, 'amount')), 'count' => count($rows), 'byDay' => $byDay, 'byStay' => $byStay, 'byType' => $byType, 'avgHours' => $hourly ? round($hours / $hourly, 1) : 0, 'heat' => $heat, 'title' => 'Reports']);
    }
    public function export()
    {
        [$from, $to] = $this->range();
        audit('export_csv', $from . ' to ' . $to);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="hotelgo-bookings-' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, ['Reference', 'Guest', 'Room type', 'Room', 'Stay', 'Check-in', 'Check-out', 'Status', 'Total']);
        $q = BookingModel::SELECT . 'WHERE b.start_at >= ? AND b.start_at < ? ORDER BY b.start_at';
        foreach (q_all($q, [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00']) as $b)
            fputcsv($out, array_map('csv_safe', [$b['booking_ref'], $b['guest_name'], $b['type_name'], $b['room_no'], stay_label($b), $b['start_at'], $b['end_at'], $b['status'], $b['total']]));
        fclose($out); exit;
    }

    /* ---------------- staff accounts ---------------- */
    public function staff()
    {
        render_admin('admin/staff', ['rows' => q_all("SELECT id, full_name, email, role, is_active, last_login_at FROM users WHERE role IN ('staff','admin') ORDER BY role, full_name"), 'title' => 'Staff & roles']);
    }
    public function staff_create()
    {
        $name = post_str('full_name'); $email = strtolower(post_str('email')); $mobile = normalize_mobile(post_str('mobile'));
        if (!len_ok($name, 2, 120) || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$mobile || $this->UserModel->by_email($email) || $this->UserModel->by_mobile($mobile)) { flash('bad', 'Please check the name, email and mobile number (they must be new).'); go('admin/staff'); }
        $temp = bin2hex(random_bytes(5)) . 'A1';
        $id = $this->UserModel->create($name, $email, $mobile, $temp, 'staff', true);
        q('UPDATE users SET mobile_verified_at = ? WHERE id = ?', [now_str(), $id]);
        audit('staff_created', $email);
        flash('ok', 'Staff account created. Temporary password (shown only once): ' . $temp . ' – the staff member must change it at first login.');
        go('admin/staff');
    }
    public function staff_toggle($id)
    {
        $id = (int) $id;
        if ($id === (int) current_user()['id']) { flash('bad', 'You cannot disable your own account.'); go('admin/staff'); }
        q("UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ? AND role = 'staff'", [$id]);
        audit('staff_toggled', '#' . $id); flash('ok', 'Staff account updated.'); go('admin/staff');
    }

    /* ---------------- audit log + settings ---------------- */
    public function audit_log()
    {
        render_admin('admin/audit', ['rows' => q_all('SELECT a.*, u.full_name, u.role FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 300'), 'title' => 'Audit log']);
    }
    public function settings()
    {
        $integrations = [
            ['Online payment gateway', env_get('PAYMENT_PROVIDER', 'mock') === 'paymongo' ? env_get('PAYMONGO_SECRET_KEY') !== '' : $this->PaymentModel->mock_allowed(), env_get('PAYMENT_PROVIDER', 'mock') === 'mock' ? 'Sandbox (simulation)' : 'PayMongo'],
            ['SMS notification service', env_get('SMS_PROVIDER') === 'semaphore' && env_get('SMS_API_KEY') !== '', env_get('SMS_PROVIDER', 'log') === 'log' ? 'Log only (simulation)' : 'Semaphore'],
            ['Google Maps API', env_get('GOOGLE_MAPS_KEY') !== '', env_get('GOOGLE_MAPS_KEY') !== '' ? 'Embedded map' : 'Link only'],
            ['Email notification service', env_get('EMAIL_PROVIDER') === 'resend' && env_get('RESEND_API_KEY') !== '', env_get('EMAIL_PROVIDER', 'log') === 'log' ? 'Log only (simulation)' : 'Resend'],
            ['Calendar (.ics file)', true, 'Built in – guests download a calendar file'],
        ];
        render_admin('admin/settings', ['integrations' => $integrations, 'keys' => ['motel_name', 'motel_address', 'motel_lat', 'motel_lng', 'booking_fee', 'hold_minutes', 'cancel_hours', 'overnight_start', 'overnight_end'], 'title' => 'Settings']);
    }
    public function settings_save()
    {
        $rules = ['motel_name' => '/^.{2,80}$/u', 'motel_address' => '/^.{2,150}$/u', 'motel_lat' => '/^-?\d{1,3}(\.\d{1,8})?$/', 'motel_lng' => '/^-?\d{1,3}(\.\d{1,8})?$/', 'booking_fee' => '/^\d{1,4}(\.\d{1,2})?$/', 'hold_minutes' => '/^\d{1,2}$/', 'cancel_hours' => '/^\d{1,2}$/', 'overnight_start' => '/^([01]\d|2[0-3]):[0-5]\d$/', 'overnight_end' => '/^([01]\d|2[0-3]):[0-5]\d$/'];
        foreach ($rules as $k => $re) {
            $v = post_str($k);
            if (!preg_match($re, $v)) { flash('bad', 'Invalid value for ' . str_replace('_', ' ', $k) . '.'); go('admin/settings'); }
            if (q_val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$k]) > 0) q('UPDATE settings SET svalue = ? WHERE skey = ?', [$v, $k]); else insert_row('settings', ['skey' => $k, 'svalue' => $v]);
        }
        audit('settings_saved'); flash('ok', 'Settings saved.'); go('admin/settings');
    }
}
