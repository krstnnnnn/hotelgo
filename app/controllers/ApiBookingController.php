<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: Dashboard and bookings (list, walk-in, edit guest details, check-in/out, cancel, delete).
| Shared helpers (token check, JSON replies) are in app/support/ApiBase.php. Business rules live in the models.
*/
class ApiBookingController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel', 'PaymentModel', 'NotificationModel']);
    }

    protected function ref(string $ref): array
    {
        if (!preg_match('/^HG-[A-Z0-9]{6}$/', $ref)) {
            $this->fail('Booking not found.', 404);
        }
        $b = $this->BookingModel->by_ref($ref);
        return $b ?: $this->fail('Booking not found.', 404);
    }

    public function dashboard()
    {
        $this->need('GET'); $this->who();
        $this->BookingModel->expire_holds();
        $board = $this->RoomModel->board();
        $this->ok($this->RoomModel->summary($board) + [
            'board' => $board, 'checkins_soon' => $this->BookingModel->checkins_soon(),
            'revenue_today' => $this->PaymentModel->total('paid', date('Y-m-d 00:00:00')),
            'upcoming' => $this->BookingModel->upcoming(8),
            'notes' => array_map(fn($n) => ['channel' => $n['channel'], 'status' => $n['status'], 'to' => mask_mobile($n['recipient']) ?: mask_email($n['recipient']), 'at' => $n['created_at']], $this->NotificationModel->recent(5)),
        ]);
    }

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
}
