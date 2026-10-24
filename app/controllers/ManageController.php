<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Owner-only area (AdminMiddleware in routes.php): rooms & rates, payments, notifications, reports, staff, audit, settings. */
class ManageController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel', 'PaymentModel', 'NotificationModel', 'UserModel', 'SettingModel', 'AuditModel']);
    }

    /* ---------------- rooms & rates ---------------- */
    public function rooms()
    {
        $edit = get_int('edit') ? $this->RoomTypeModel->type(get_int('edit')) : null;
        render_admin('admin/rooms', ['types' => $this->RoomTypeModel->types(false), 'rooms' => $this->RoomModel->rooms(), 'edit' => $edit, 'title' => 'Rooms & rates']);
    }
    public function save_type()
    {
        $id = post_int('id');
        $d  = $this->RoomTypeModel->clean($_POST);
        if (!$d) { flash('bad', 'Please check the name, guests and prices (numbers only).'); go('admin/rooms' . ($id ? '?edit=' . $id : '')); }
        $this->RoomTypeModel->save($id ?: null, $d);
        audit($id ? 'room_type_updated' : 'room_type_created', $d['name']);
        flash('ok', 'Room type saved.');
        go('admin/rooms');
    }
    public function add_room()
    {
        [$d, $why] = $this->RoomModel->clean_room(['room_no' => post_str('room_no'), 'room_type_id' => post_int('type'), 'floor' => post_int('floor', 1)]);
        if (!$d) { flash('bad', $why); go('admin/rooms'); }
        $this->RoomModel->add_room($d['room_no'], $d['room_type_id'], $d['floor']);
        audit('room_added', $d['room_no']); flash('ok', 'Room added.'); go('admin/rooms');
    }
    public function room_status($id)
    {
        $s = post_str('status');
        if (!in_array($s, RoomModel::STATUSES, true)) abort(400, 'Invalid status.');
        $this->RoomModel->set_status((int) $id, $s); audit('room_status', '#' . (int) $id . ' ' . $s);
        flash('ok', 'Room updated.'); go('admin/rooms');
    }

    /* ---------------- payments ---------------- */
    public function payments()
    {
        render_admin('admin/payments', [
            'rows' => $this->PaymentModel->recent(200), 'paid_today' => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'refunded' => $this->PaymentModel->total('refunded'), 'pending' => $this->PaymentModel->waiting_count(), 'title' => 'Payments',
        ]);
    }
    public function refund($id)
    {
        [$ok, $msg] = $this->PaymentModel->refund((int) $id);
        audit($ok ? 'refund' : 'refund_failed', 'payment #' . (int) $id);
        flash($ok ? 'ok' : 'bad', $ok ? 'Payment refunded and the booking was cancelled.' : $msg);
        go('admin/payments');
    }

    /* ---------------- notifications ---------------- */
    public function notifications()
    {
        render_admin('admin/notifications', ['rows' => $this->NotificationModel->recent(200), 'title' => 'Notifications']);
    }
    public function notification_retry($id)
    {
        if ($this->NotificationModel->retry((int) $id)) flash('ok', 'Sent again.');
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
        render_admin('admin/reports', $this->PaymentModel->report($from, $to) + ['title' => 'Reports']);
    }
    public function export()
    {
        [$from, $to] = $this->range();
        audit('export_csv', $from . ' to ' . $to);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="hotelgo-bookings-' . $from . '_' . $to . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, ['Reference', 'Guest', 'Room type', 'Room', 'Stay', 'Check-in', 'Check-out', 'Status', 'Total']);
        foreach ($this->BookingModel->starting_between($from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00') as $b)
            fputcsv($out, array_map('csv_safe', [$b['booking_ref'], $b['guest_name'], $b['type_name'], $b['room_no'], stay_label($b), $b['start_at'], $b['end_at'], $b['status'], $b['total']]));
        fclose($out); exit;
    }

    /* ---------------- staff accounts ---------------- */
    public function staff()
    {
        render_admin('admin/staff', ['rows' => $this->UserModel->staff_list(), 'title' => 'Staff & roles']);
    }
    public function staff_create()
    {
        [$ok, $msg, $temp] = $this->UserModel->create_staff(post_str('full_name'), post_str('email'), post_str('mobile'));
        if (!$ok) { flash('bad', $msg); go('admin/staff'); }
        audit('staff_created', strtolower(post_str('email')));
        flash('ok', 'Staff account created. Temporary password (shown only once): ' . $temp . ' – the staff member must change it at first login.');
        go('admin/staff');
    }
    public function staff_toggle($id)
    {
        $id = (int) $id;
        if ($id === (int) current_user()['id']) { flash('bad', 'You cannot disable your own account.'); go('admin/staff'); }
        $this->UserModel->toggle_staff($id);
        audit('staff_toggled', '#' . $id); flash('ok', 'Staff account updated.'); go('admin/staff');
    }

    /* ---------------- audit log + settings ---------------- */
    public function audit_log()
    {
        render_admin('admin/audit', ['rows' => $this->AuditModel->recent(300), 'title' => 'Audit log']);
    }
    public function settings()
    {
        $this->call->library('integrations');
        render_admin('admin/settings', ['integrations' => $this->integrations->status(), 'keys' => array_keys(SettingModel::RULES), 'title' => 'Settings']);
    }
    public function settings_save()
    {
        $error = $this->SettingModel->save_all($_POST);
        if ($error) { flash('bad', $error); go('admin/settings'); }
        audit('settings_saved'); flash('ok', 'Settings saved.'); go('admin/settings');
    }

    /* ---------------- "Send test" buttons (admin only, max 5 per 10 minutes) ---------------- */
    private function test_allowed(string $action): bool
    {
        $since = date('Y-m-d H:i:s', time() - (int) config_item('hotelgo_test_window'));
        if ($this->AuditModel->count_since((int) current_user()['id'], $action, $since) >= (int) config_item('hotelgo_test_limit')) { flash('bad', 'Too many tests. Please wait a few minutes.'); return false; }
        return true;
    }
    private function test_result(int $id): void
    {
        $n = $this->NotificationModel->find($id);
        if ($n['status'] === 'sent') flash('ok', 'Sent! Check the phone / inbox. (Provider accepted the message.)');
        elseif ($n['status'] === 'logged') flash('warn', 'Simulation mode: the message was only saved in Notifications. Set the provider and API key in Render → Environment to send for real.');
        else flash('bad', 'Failed: ' . $n['error']);
    }
    public function test_sms()
    {
        $m = normalize_mobile(post_str('mobile'));
        if (!$m) { flash('bad', 'Enter a valid Philippine mobile number.'); go('admin/settings'); }
        if (!$this->test_allowed('test_sms')) go('admin/settings');
        audit('test_sms', mask_mobile($m));
        $this->test_result($this->NotificationModel->send(null, (int) current_user()['id'], 'sms', $m, '', 'HOTELGO: this is a message from your admin settings page. SMS is working!'));
        go('admin/settings');
    }
    public function test_email()
    {
        $e = strtolower(post_str('email'));
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) { flash('bad', 'Enter a valid email address.'); go('admin/settings'); }
        if (!$this->test_allowed('test_email')) go('admin/settings');
        audit('test_email', mask_email($e));
        $this->test_result($this->NotificationModel->send(null, (int) current_user()['id'], 'email', $e, 'HOTELGO email test', "Hello!\n\nThis is a test email from the HOTELGO admin settings page. Email is working.\n"));
        go('admin/settings');
    }
}
