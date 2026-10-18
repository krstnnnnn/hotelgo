<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Front-desk area. Only staff + admin can reach it (StaffMiddleware in routes.php). */
class AdminController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        foreach (['RoomModel', 'BookingModel', 'NotificationModel', 'UserModel'] as $m) $this->call->model($m);
    }
    private function ref_ok(string $ref): void { if (!preg_match('/^HG-[A-Z0-9]{6}$/', $ref)) abort(404); }

    public function dashboard()
    {
        $this->BookingModel->expire_holds();
        $board = $this->RoomModel->board();
        $count = array_count_values(array_column($board, 'state'));
        $total = count($board) ?: 1;
        $today = date('Y-m-d 00:00:00');
        render_admin('admin/dashboard', [
            'board' => $board, 'count' => $count, 'total' => $total,
            'occupancy' => (int) round((($count['occupied'] ?? 0) + ($count['reserved'] ?? 0)) / $total * 100),
            'checkins' => (int) q_val("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed' AND start_at BETWEEN ? AND ?", [now_str(), date('Y-m-d H:i:s', time() + 7200)]),
            'revenue' => (float) q_val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'paid' AND paid_at >= ?", [$today]),
            'upcoming' => q_all(BookingModel::SELECT . "WHERE b.status = 'confirmed' AND b.start_at BETWEEN ? AND ? ORDER BY b.start_at LIMIT 8", [date('Y-m-d H:i:s', time() - 1800), date('Y-m-d H:i:s', time() + 6 * 3600)]),
            'notes' => q_all('SELECT * FROM notifications ORDER BY id DESC LIMIT 5'),
            'title' => 'Dashboard',
        ]);
    }

    /* ---------------- reservations ---------------- */
    public function reservations()
    {
        $status = get_str('status'); $search = get_str('q');
        $allowed = ['', 'pending_payment', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'expired'];
        if (!in_array($status, $allowed, true)) $status = '';
        $sql = BookingModel::SELECT . 'WHERE 1 = 1'; $args = [];
        if ($status !== '') { $sql .= ' AND b.status = ?'; $args[] = $status; }
        if ($search !== '') { $sql .= ' AND (b.booking_ref = ? OR b.guest_name LIKE ?)'; $args[] = strtoupper($search); $args[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%'; }
        render_admin('admin/reservations', ['rows' => q_all($sql . ' ORDER BY b.start_at DESC LIMIT 200', $args), 'status' => $status, 'search' => $search, 'title' => 'Reservations']);
    }
    public function reservation($ref)
    {
        $this->ref_ok($ref);
        $b = $this->BookingModel->find($ref);
        if (!$b) abort(404, 'Booking not found.');
        $type = q_one('SELECT cleaning_buffer_min FROM room_types WHERE id = ?', [$b['room_type_id']]);
        render_admin('admin/reservation', [
            'b' => $b, 'free' => $this->BookingModel->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) $type['cleaning_buffer_min'], (int) $b['id']),
            'payments' => q_all('SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC', [$b['id']]),
            'notes' => q_all('SELECT * FROM notifications WHERE booking_id = ? ORDER BY id DESC', [$b['id']]),
            'title' => 'Booking ' . $ref,
        ]);
    }
    public function reservation_action($ref)
    {
        $this->ref_ok($ref);
        $b = $this->BookingModel->find($ref);
        if (!$b) abort(404, 'Booking not found.');
        $do = post_str('do');
        if ($do === 'checkin') { $ok = $this->BookingModel->check_in((int) $b['id']); flash($ok ? 'ok' : 'bad', $ok ? 'Guest checked in.' : 'Only confirmed bookings can be checked in.'); }
        elseif ($do === 'checkout') { $ok = $this->BookingModel->check_out((int) $b['id']); flash($ok ? 'ok' : 'bad', $ok ? 'Guest checked out. Room is now being cleaned.' : 'Only checked-in guests can be checked out.'); }
        elseif ($do === 'cancel') {
            if ($this->BookingModel->can_cancel($b, true)) { $this->BookingModel->cancel($b); $this->NotificationModel->booking_cancelled($b); flash('ok', 'Booking cancelled and payment marked as refunded.'); }
            else flash('bad', 'This booking cannot be cancelled.');
        }
        elseif ($do === 'resend') {
            if ($b['status'] === 'confirmed' || $b['status'] === 'checked_in') { $this->NotificationModel->booking_confirmed($b); flash('ok', 'Confirmation sent again.'); } else flash('bad', 'Nothing to resend for this status.');
        }
        elseif ($do === 'reassign') {
            $ok = in_array($b['status'], ['confirmed', 'pending_payment'], true) && $this->BookingModel->reassign($b, post_int('room_id'));
            flash($ok ? 'ok' : 'bad', $ok ? 'Room changed.' : 'That room is not free for this stay.');
        } else abort(400, 'Unknown action.');
        audit('reservation_' . $do, $ref);
        go('admin/reservations/' . $ref);
    }

    /* ---------------- walk-in booking (guest is at the front desk, pays cash) ---------------- */
    public function walkin_form()
    {
        render_admin('admin/walkin', ['types' => $this->RoomModel->types(), 'old' => [], 'title' => 'Walk-in booking']);
    }
    public function walkin()
    {
        $type = $this->RoomModel->type(post_int('type'));
        $stay = post_str('stay'); $hours = post_int('hours', 3); $guests = post_int('guests', 1);
        $w = $this->BookingModel->window($stay, date('Y-m-d'), date('H:i'), $hours);
        $name = post_str('name'); $mobile = normalize_mobile(post_str('mobile')); $err = [];
        if (!$type || !(int) $type['is_active']) $err[] = 'Choose a room type.';
        if (!$w) $err[] = 'Choose hourly (3, 6 or 12 hours) or overnight.';
        if ($type && ($guests < 1 || $guests > (int) $type['max_guests'])) $err[] = 'Number of guests is too many for that room type.';
        if (!len_ok($name, 2, 120)) $err[] = 'Enter the guest name.';
        if (!$mobile) $err[] = 'Enter a valid mobile number.';
        if ($err) { foreach ($err as $m) flash('bad', $m); render_admin('admin/walkin', ['types' => $this->RoomModel->types(), 'old' => $_POST, 'title' => 'Walk-in booking']); return; }
        $f = ['name' => $name, 'mobile' => $mobile, 'email' => '', 'id_type' => '', 'id_last4' => '', 'guests' => $guests];
        $res = $this->BookingModel->create(null, $type, $w, $f, $stay, 'walkin', 'confirmed', 'cash');
        if (!$res['ok']) { flash('bad', $res['error']); go('admin/walkin'); }
        $b = $this->BookingModel->find($res['ref']);
        q('UPDATE bookings SET hold_expires_at = NULL WHERE id = ?', [$b['id']]);
        insert_row('payments', ['booking_id' => $b['id'], 'txn_ref' => 'TXN-' . strtoupper(bin2hex(random_bytes(5))), 'method' => 'cash', 'provider' => 'frontdesk', 'provider_ref' => '', 'amount' => $b['total'], 'status' => 'paid', 'created_at' => now_str(), 'paid_at' => now_str()]);
        $this->NotificationModel->booking_confirmed($b);
        audit('walkin_created', $b['booking_ref']);
        flash('ok', 'Walk-in booked: Room ' . $b['room_no'] . ' (' . $b['booking_ref'] . ').');
        go('admin/reservations/' . $b['booking_ref']);
    }

    /* ---------------- guests (personal data: every view is written to the audit log) ---------------- */
    public function guests()
    {
        $s = get_str('q'); $sql = "SELECT u.id, u.full_name, u.email, u.mobile, u.created_at, (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status <> 'expired') AS stays,
            (SELECT MAX(b.start_at) FROM bookings b WHERE b.user_id = u.id) AS last_visit FROM users u WHERE u.role = 'guest'"; $args = [];
        if ($s !== '') { $sql .= ' AND (u.full_name LIKE ? OR u.email = ?)'; $args[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $s) . '%'; $args[] = strtolower($s); }
        render_admin('admin/guests', ['rows' => q_all($sql . ' ORDER BY u.created_at DESC LIMIT 100', $args), 'search' => $s, 'title' => 'Guest records']);
    }
    public function guest($id)
    {
        $g = q_one("SELECT id, full_name, email, mobile, created_at, staff_note FROM users WHERE id = ? AND role = 'guest'", [(int) $id]);
        if (!$g) abort(404, 'Guest not found.');
        audit('view_guest', 'user #' . $g['id']);
        render_admin('admin/guest', ['g' => $g, 'rows' => q_all(BookingModel::SELECT . 'WHERE b.user_id = ? ORDER BY b.start_at DESC LIMIT 50', [$g['id']]), 'title' => $g['full_name']]);
    }
    public function guest_note($id)
    {
        $note = mb_substr(post_str('note'), 0, 500);
        q("UPDATE users SET staff_note = ? WHERE id = ? AND role = 'guest'", [$note, (int) $id]);
        audit('guest_note', 'user #' . (int) $id);
        flash('ok', 'Note saved.');
        go('admin/guests/' . (int) $id);
    }
}
