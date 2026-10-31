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
        $this->call->model(['RoomTypeModel', 'RoomModel', 'BookingModel', 'PaymentModel', 'NotificationModel', 'HotelModel', 'ServiceRequestModel']);
        $this->call->library('paymongo');
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
            'week' => $this->week_revenue(),
            'badges' => ['pending' => $this->PaymentModel->waiting_count(), 'requests' => $this->ServiceRequestModel->count_new()],
            'hotels' => \Hotelgo\Support\Scope::platform() ? array_map(fn($h) => ['id' => (int) $h['id'], 'name' => $h['name'], 'area' => $h['area'] ?? ''], $this->HotelModel->all()) : [],
            'hotel_stats' => $this->hotel_stats($board),
            'notes' => array_map(fn($n) => ['channel' => $n['channel'], 'status' => $n['status'], 'to' => mask_mobile($n['recipient']) ?: mask_email($n['recipient']), 'at' => $n['created_at']], $this->NotificationModel->recent(5)),
        ]);
    }

    /* Revenue of this week (Monday to Sunday), hourly and overnight apart, for the line chart */
    private function week_revenue(): array
    {
        $mon = strtotime('monday this week');
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime('+' . $i . ' day', $mon));
            $days[$d] = ['day' => date('D', strtotime($d)), 'date' => $d, 'hourly' => 0.0, 'overnight' => 0.0, 'is_today' => $d === date('Y-m-d'), 'is_future' => $d > date('Y-m-d')];
        }
        foreach ($this->PaymentModel->paid_between(array_key_first($days), array_key_last($days)) as $p) {
            $d = substr((string) $p['paid_at'], 0, 10);
            if (isset($days[$d])) {
                $days[$d][$p['stay_type'] === 'overnight' ? 'overnight' : 'hourly'] += (float) $p['amount'];
            }
        }
        return array_values($days);
    }
    /* Per hotel: rooms, occupied rooms and revenue today (platform view) */
    private function hotel_stats(array $board): array
    {
        $out = [];
        foreach ($board as $r) {
            $h = (int) ($r['hotel_id'] ?? 0);
            $out[$h] ??= ['hotel_id' => $h, 'rooms' => 0, 'occupied' => 0];
            $out[$h]['rooms']++;
            $out[$h]['occupied'] += $r['state'] === 'occupied' ? 1 : 0;
        }
        foreach ($out as &$o) {
            $o['revenue_today'] = 0.0;
        }
        unset($o);
        foreach ($this->PaymentModel->paid_between(date('Y-m-d'), date('Y-m-d')) as $p) {
            $h = (int) ($p['hotel_id'] ?? 0);
            if (isset($out[$h])) {
                $out[$h]['revenue_today'] += (float) $p['amount'];
            }
        }
        return array_values($out);
    }

    public function bookings()                       // GET list
    {
        $this->need('GET'); $this->who();
        $q = $this->api->get_query_params();
        $status = (string) ($q['status'] ?? '');
        if (!in_array($status, array_merge([''], BookingModel::STATUSES), true)) $status = '';
        $this->BookingModel->expire_holds();
        $this->ok(['data' => $this->BookingModel->search($status, (string) ($q['q'] ?? ''), 200, ['stay' => (string) ($q['stay'] ?? ''), 'day' => (string) ($q['day'] ?? ''), 'hotel_id' => (int) ($q['hotel_id'] ?? 0)]), 'now' => date('Y-m-d H:i:s')]);
    }

    public function booking($ref)                    // GET one
    {
        $this->need('GET'); $this->who();
        $b = $this->ref($ref);
        $type = $this->RoomTypeModel->type((int) $b['room_type_id']);
        $this->ok(['data' => $b,
            'free_rooms' => $this->BookingModel->free_rooms((int) $b['room_type_id'], $b['start_at'], $b['end_at'], (int) ($type['cleaning_buffer_min'] ?? 0), (int) $b['id']),
            'payments' => $this->PaymentModel->for_booking((int) $b['id']),
            'requests' => array_map(fn($r) => $r + ['owing' => $this->ServiceRequestModel->owing($r)], $this->ServiceRequestModel->for_booking((int) $b['id'])),
            'online' => $this->paymongo->configured(),
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
        $online = ($in['pay'] ?? 'cash') === 'online';
        if ($online && !$this->paymongo->configured()) $this->fail('Online payment is not switched on (set PAYMONGO_SECRET_KEY).', 422);
        $res = $this->BookingModel->walkin($type, $w, ['name' => $name, 'mobile' => $mobile, 'email' => '', 'id_type' => '', 'id_last4' => '', 'guests' => $guests], $stay, $online);
        if (!$res['ok']) $this->fail($res['error'], 409);
        if (!$online) $this->NotificationModel->booking_confirmed($res['booking']);
        $this->log($online ? 'walkin_online_started' : 'walkin_created', $res['booking']['booking_ref']);
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

    /* POST /api/bookings/{ref}/pay  {do: link | wallet | check | cash, wallet?}
       link / wallet: start an online payment (returns a web address for the guest's phone); check: ask PayMongo; cash: paid at the desk */
    public function booking_pay($ref)
    {
        $this->need('POST'); $this->who();
        $this->BookingModel->expire_holds();
        $b = $this->ref($ref); $in = $this->body(); $do = (string) ($in['do'] ?? '');
        if (!in_array($do, ['link', 'wallet', 'check', 'cash'], true)) $this->fail('Unknown payment action.', 422);
        $online = $this->paymongo->configured();
        if ($b['status'] !== 'pending_payment') {
            $this->ok(['state' => $b['status'], 'message' => 'This booking is not waiting for payment.', 'data' => $b]);
        }
        if ($do === 'cash') {
            $r = $this->BookingModel->mark_paid($ref, 'cash', 'cash', '', false);
            if ($r['status'] !== 'confirmed') $this->fail('The room hold ended, so this booking can no longer be paid. Create it again.', 409);
            $this->NotificationModel->booking_confirmed($r['booking']);
            $this->log('payment_cash', $ref);
            $this->ok(['state' => 'paid', 'message' => 'Cash received. Booking confirmed.', 'data' => $r['booking']]);
        }
        if (!$online) $this->fail('Online payment is not switched on (set PAYMONGO_SECRET_KEY).', 422);
        if ($do === 'check') {
            $s = $this->PaymentModel->settle($b);
            if ($s['result'] && in_array($s['result']['status'], ['confirmed', 'confirmed_late'], true)) {
                $this->NotificationModel->booking_confirmed($s['result']['booking']);
                $this->log('payment_paid', $ref);
            }
            $this->ok(['state' => $s['state'], 'message' => $s['state'] === 'paid' ? 'Payment received.' : ($s['state'] === 'unreachable' ? 'Could not reach PayMongo. Try again.' : 'Not paid yet.'), 'data' => $this->BookingModel->by_ref($ref)]);
        }
        $url = $do === 'link' ? $this->PaymentModel->start_link($b) : (in_array((string) ($in['wallet'] ?? ''), $this->paymongo->wallets(), true) ? $this->PaymentModel->start_wallet($b, (string) $in['wallet']) : $this->fail('Choose an e-wallet.', 422));
        if (!$url) { $this->log('payment_start_failed', $this->PaymentModel->error); $this->fail('PayMongo did not start the payment. ' . $this->PaymentModel->error, 502); }
        $this->log('payment_started_' . $do, $ref);
        $this->ok(['state' => 'pending', 'url' => $url, 'message' => 'Payment started.', 'data' => $this->BookingModel->by_ref($ref)]);
    }
}
