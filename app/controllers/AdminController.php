<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Front-desk area. Only staff + admin can reach it (StaffMiddleware in routes.php). */
class AdminController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel', 'NotificationModel', 'UserModel', 'PaymentModel']);
    }
    private function ref_ok(string $ref): void { if (!preg_match('/^HG-[A-Z0-9]{6}$/', $ref)) abort(404); }

    public function dashboard()
    {
        $this->BookingModel->expire_holds();
        $board = $this->RoomModel->board();
        render_admin('admin/dashboard', $this->RoomModel->summary($board) + [
            'board'    => $board,
            'checkins' => $this->BookingModel->checkins_soon(),
            'revenue'  => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'upcoming' => $this->BookingModel->upcoming(8),
            'notes'    => $this->NotificationModel->recent(5),
            'title'    => 'Dashboard',
        ]);
    }

    /* ---------------- reservations ---------------- */
    public function reservations()
    {
        $status = get_str('status'); $search = get_str('q');
        if (!in_array($status, array_merge([''], BookingModel::STATUSES), true)) $status = '';
        render_admin('admin/reservations', ['rows' => $this->BookingModel->search($status, $search), 'status' => $status, 'search' => $search, 'title' => 'Reservations']);
    }
    public function reservation($ref)
    {
        $this->ref_ok($ref);
        $b = $this->BookingModel->by_ref($ref);
        if (!$b) abort(404, 'Booking not found.');
        $type = $this->RoomTypeModel->type((int) $b['room_type_id']);
        render_admin('admin/reservation', [
            'b' => $b, 'free' => $this->BookingModel->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) $type['cleaning_buffer_min'], (int) $b['id']),
            'payments' => $this->PaymentModel->for_booking((int) $b['id']),
            'notes' => $this->NotificationModel->for_booking((int) $b['id']),
            'title' => 'Booking ' . $ref,
        ]);
    }
    public function reservation_action($ref)
    {
        $this->ref_ok($ref);
        $b = $this->BookingModel->by_ref($ref);
        if (!$b) abort(404, 'Booking not found.');
        $do = post_str('do');
        if (!in_array($do, ['checkin', 'checkout', 'cancel', 'resend', 'reassign'], true)) abort(400, 'Unknown action.');
        [$level, $message] = $this->BookingModel->staff_action($b, $do, post_int('room_id'));
        flash($level, $message);
        audit('reservation_' . $do, $ref);
        go('admin/reservations/' . $ref);
    }

    /* ---------------- walk-in booking (guest is at the front desk, pays cash) ---------------- */
    public function walkin_form()
    {
        render_admin('admin/walkin', ['types' => $this->RoomTypeModel->types(), 'old' => [], 'title' => 'Walk-in booking']);
    }
    public function walkin()
    {
        $type = $this->RoomTypeModel->type(post_int('type'));
        $stay = post_str('stay'); $hours = post_int('hours', 3); $guests = post_int('guests', 1);
        $w = $this->BookingModel->window($stay, date('Y-m-d'), date('H:i'), $hours);
        $name = post_str('name'); $mobile = normalize_mobile(post_str('mobile')); $err = [];
        if (!$type || !(int) $type['is_active']) $err[] = 'Choose a room type.';
        if (!$w) $err[] = 'Choose hourly (3, 6 or 12 hours) or overnight.';
        if ($type && ($guests < 1 || $guests > (int) $type['max_guests'])) $err[] = 'Number of guests is too many for that room type.';
        if (!len_ok($name, 2, 120)) $err[] = 'Enter the guest name.';
        if (!$mobile) $err[] = 'Enter a valid mobile number.';
        if ($err) { foreach ($err as $m) flash('bad', $m); render_admin('admin/walkin', ['types' => $this->RoomTypeModel->types(), 'old' => $_POST, 'title' => 'Walk-in booking']); return; }
        $f = ['name' => $name, 'mobile' => $mobile, 'email' => '', 'id_type' => '', 'id_last4' => '', 'guests' => $guests];
        $res = $this->BookingModel->walkin($type, $w, $f, $stay);
        if (!$res['ok']) { flash('bad', $res['error']); go('admin/walkin'); }
        $b = $res['booking'];
        $this->NotificationModel->booking_confirmed($b);
        audit('walkin_created', $b['booking_ref']);
        flash('ok', 'Walk-in booked: Room ' . $b['room_no'] . ' (' . $b['booking_ref'] . ').');
        go('admin/reservations/' . $b['booking_ref']);
    }

    /* ---------------- guests (personal data: every view is written to the audit log) ---------------- */
    public function guests()
    {
        $s = get_str('q');
        render_admin('admin/guests', ['rows' => $this->UserModel->guests($s), 'search' => $s, 'title' => 'Guest records']);
    }
    public function guest($id)
    {
        $g = $this->UserModel->guest((int) $id);
        if (!$g) abort(404, 'Guest not found.');
        audit('view_guest', 'user #' . $g['id']);
        render_admin('admin/guest', ['g' => $g, 'rows' => $this->BookingModel->for_user((int) $g['id']), 'title' => $g['full_name']]);
    }
    public function guest_note($id)
    {
        $this->UserModel->save_note((int) $id, post_str('note'));
        audit('guest_note', 'user #' . (int) $id);
        flash('ok', 'Note saved.');
        go('admin/guests/' . (int) $id);
    }
}
