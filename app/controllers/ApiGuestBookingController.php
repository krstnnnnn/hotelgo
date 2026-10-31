<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| HOTELGO JSON API: what a signed-in GUEST does with bookings. A guest can only ever open HIS OWN bookings (the owner is part of the query).
|   GET  /api/guest/quote?type=&stay=&date=&time=&hours=&guests=     price, fee, window and free rooms before booking
|   POST /api/guest/bookings                                          create a booking (held, waiting for payment)
|   GET  /api/guest/bookings?tab=upcoming|past|cancelled              my bookings
|   GET  /api/guest/bookings/{ref}                                    one booking (+ package, requests, QR, last email)
|   POST /api/guest/bookings/{ref}/cancel      POST .../modify {date,time}
|   GET  /api/guest/bookings/{ref}/pay                                how to pay (provider, wallets, time left)
|   POST /api/guest/bookings/{ref}/pay        { do: wallet|link|confirm|check }
|   POST /api/guest/bookings/{ref}/requests   { service, note }       VIP guests: ask for an extra service
|   POST /api/guest/bookings/{ref}/requests/{id}/pay                  pay the extra price online (payment link)
|   GET  /api/guest/bookings/{ref}/receipt    GET .../calendar        receipt data, and the .ics calendar file
*/
class ApiGuestBookingController extends \Hotelgo\Support\ApiBase
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'BookingModel', 'PaymentModel', 'NotificationModel', 'ServiceRequestModel']);
        $this->call->library('paymongo');
    }

    /* ---------------- helpers ---------------- */
    private function mine(string $ref): array
    {
        $me = $this->member();
        $b = preg_match('/^HG-[A-Z0-9]{6}$/', $ref) ? $this->BookingModel->find_mine($ref, (int) $me['id']) : null;   // user id comes from the TOKEN, never from the request
        return $b ?: $this->fail('We could not find that booking.', 404);
    }
    private function clean_booking(array $b): array
    {
        return array_diff_key($b, array_flip(['pay_ref', 'user_id', 'gcal_event_id', 'gcal_synced_at']));
    }
    /* read + check the search values (prices are always recalculated here, never trusted from the browser) */
    private function search_values(array $src): ?array
    {
        $stay = (string) ($src['stay'] ?? ''); $hours = (isset($src['hours']) && ctype_digit((string) $src['hours'])) ? (int) $src['hours'] : 3;
        $date = (string) ($src['date'] ?? ''); $time = (string) ($src['time'] ?? '19:00');
        $guests = (isset($src['guests']) && ctype_digit((string) $src['guests'])) ? (int) $src['guests'] : 0;
        $typeId = (isset($src['type']) && ctype_digit((string) $src['type'])) ? (int) $src['type'] : 0;
        $type = $typeId ? $this->RoomTypeModel->type($typeId) : null;
        $w = $this->BookingModel->window($stay, $date, $time, $hours);
        if (!$type || !(int) $type['is_active'] || !$w || $guests < 1 || $guests > (int) $type['max_guests']) return null;
        return ['p' => ['stay' => $stay, 'date' => $date, 'time' => $time, 'hours' => $hours, 'guests' => $guests, 'type' => $typeId], 'type' => $type, 'w' => $w];
    }
    private function money_part(array $sv): array
    {
        $rate = $this->BookingModel->price($sv['type'], $sv['p']['stay'], (int) $sv['w']['hours']); $fee = (float) setting('booking_fee', '30');
        return ['rate' => $rate, 'fee' => $fee, 'total' => $rate + $fee];
    }
    private function online(): bool { return $this->paymongo->configured(); }

    /* ---------------- booking ---------------- */
    public function quote()
    {
        $this->need('GET'); $this->member();
        $sv = $this->search_values($this->api->get_query_params()) ?: $this->fail('Please search again: the room or time was not valid.', 422);
        $this->BookingModel->expire_holds();
        $free = count($this->BookingModel->free_rooms((int) $sv['type']['id'], $sv['w']['start'], $sv['w']['end'], (int) $sv['type']['cleaning_buffer_min']));
        $this->ok(['search' => $sv['p'], 'type' => $sv['type'], 'window' => $sv['w'], 'free' => $free, 'hold_minutes' => (int) setting('hold_minutes', '10'),
            'cancel_hours' => (int) setting('cancel_hours', '2')] + $this->money_part($sv));
    }

    public function create()
    {
        $this->need('POST'); $me = $this->member();
        if (!$this->online()) $this->fail('Online booking is not open yet because online payment is not set up. Please contact the motel.', 503);
        $in = $this->body();
        $sv = $this->search_values($in) ?: $this->fail('Your search was not valid. Please search again.', 422);
        $f = ['name' => trim((string) ($in['name'] ?? '')), 'mobile' => normalize_mobile(trim((string) ($in['mobile'] ?? ''))), 'email' => strtolower(trim((string) ($in['email'] ?? ''))),
            'id_type' => (string) ($in['id_type'] ?? ''), 'guests' => $sv['p']['guests']];
        $idNum = preg_replace('/[^A-Za-z0-9]/', '', (string) ($in['id_number'] ?? ''));
        $method = (string) ($in['pay_method'] ?? ''); $err = [];
        if (!len_ok($f['name'], 2, 120)) $err[] = 'Please enter the guest full name.';
        if (!$f['mobile']) $err[] = 'Please enter a valid Philippine mobile number.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Please enter a valid email address.';
        if (!in_array($f['id_type'], ["Driver's license", 'Passport', 'UMID', 'National ID', 'Other government ID'], true)) $err[] = 'Please choose a valid ID type.';
        if (strlen($idNum) < 4 || strlen($idNum) > 30) $err[] = 'Please enter your ID number.';
        if (!in_array($method, ['ewallet', 'card', 'bank'], true)) $err[] = 'Please choose a payment method.';
        if (empty($in['agree'])) $err[] = 'Please accept the house rules and cancellation policy.';
        if ($err) $this->fail(implode(' ', $err), 422);
        $f['id_last4'] = substr($idNum, -4);      // we keep ONLY the last 4 characters of the ID number (data privacy)
        $res = $this->BookingModel->create($me, $sv['type'], $sv['w'], $f, $sv['p']['stay'], 'online', 'pending_payment', $method);
        if (!$res['ok']) $this->fail($res['error'], 409);
        $this->log('booking_created', $res['ref']);
        $this->ok(['booking_ref' => $res['ref'], 'status' => 'pending_payment'], 201);
    }

    public function bookings()
    {
        $this->need('GET'); $me = $this->member();
        $q = $this->api->get_query_params(); $tab = in_array($q['tab'] ?? 'upcoming', ['upcoming', 'past', 'cancelled'], true) ? $q['tab'] ?? 'upcoming' : 'upcoming';
        $this->BookingModel->expire_holds();
        $this->ok(['tab' => $tab, 'data' => array_map(fn($b) => $this->clean_booking($b), $this->BookingModel->mine((int) $me['id'], $tab))]);
    }

    public function booking($ref)
    {
        $this->need('GET'); $b = $this->mine($ref);
        $latest = fn(string $ch) => ($m = $this->NotificationModel->latest((int) $b['id'], $ch)) ? ['status' => $m['status'], 'recipient' => $m['recipient'], 'subject' => $m['subject'] ?? '', 'message' => $m['message'] ?? '', 'created_at' => $m['created_at']] : null;
        $reqs = $this->ServiceRequestModel->for_booking((int) $b['id']);
        $this->ServiceRequestModel->settle_many(array_map(fn($r) => $r + ['booking_ref' => $b['booking_ref']], $reqs));        // a guest who just paid an extra link is shown as paid
        $reqs = array_map(fn($r) => $r + ['owing' => $this->ServiceRequestModel->owing($r)], $this->ServiceRequestModel->for_booking((int) $b['id']));
        $ok = in_array($b['status'], ['confirmed', 'checked_in'], true);
        $this->ok(['data' => $this->clean_booking($b), 'can_cancel' => $this->BookingModel->can_cancel($b), 'can_modify' => $b['status'] === 'confirmed' && $this->BookingModel->can_cancel($b),
            'mail' => $latest('email'), 'requests' => $reqs, 'services' => $this->ServiceRequestModel->services(), 'online' => $this->online(),
            'included' => included_list($b), 'qr' => $ok ? qr_svg(front_url('bookings/' . $b['booking_ref'])) : null,
            'hotel' => ['name' => hotel_of($b, 'name'), 'address' => hotel_of($b, 'address'), 'lat' => (float) hotel_of($b, 'lat'), 'lng' => (float) hotel_of($b, 'lng')],
            'cancel_hours' => (int) setting('cancel_hours', '2')]);
    }

    public function cancel($ref)
    {
        $this->need('POST'); $b = $this->mine($ref);
        if (!$this->BookingModel->can_cancel($b)) $this->fail('This booking can no longer be cancelled online. Please contact the motel.', 409);
        $this->BookingModel->cancel($b);
        $refunded = $this->PaymentModel->refund_booking($b);
        $this->log('booking_cancelled', $ref);
        if ($b['status'] === 'confirmed') $this->NotificationModel->booking_cancelled($b);
        $this->ok(['message' => $refunded ? 'Booking ' . $ref . ' was cancelled.' : 'Booking ' . $ref . ' was cancelled. Your refund is being processed by the motel.', 'refunded' => (bool) $refunded]);
    }

    public function modify($ref)
    {
        $this->need('POST'); $b = $this->mine($ref); $in = $this->body();
        if ($b['status'] !== 'confirmed' || !$this->BookingModel->can_cancel($b)) $this->fail('This booking can no longer be changed online. Please contact the motel.', 409);
        $w = $this->BookingModel->window($b['stay_type'], (string) ($in['date'] ?? ''), (string) ($in['time'] ?? date('H:i', strtotime($b['start_at']))), (int) $b['hours']);
        if (!$w) $this->fail('Please choose a valid date and time (not in the past, within 60 days).', 422);
        $res = $this->BookingModel->reschedule($b, $w);
        if (!$res['ok']) $this->fail($res['error'], 409);
        $this->log('booking_rescheduled', $ref);
        $this->NotificationModel->booking_rescheduled($this->BookingModel->by_ref($ref));
        $this->ok(['message' => 'Your booking was moved to the new time.', 'data' => $this->clean_booking($this->mine($ref))]);
    }

    /* ---------------- payment ---------------- */
    /* what happened after the gateway said something: [state, message] */
    private function finish(array $r): array
    {
        $b = $r['booking']; $st = $r['status'];
        if ($st === 'confirmed' || $st === 'confirmed_late') {
            $this->log('payment_paid', $b['booking_ref']);
            $this->NotificationModel->booking_confirmed($b);
            return ['paid', 'Payment received. Your room has been assigned!'];
        }
        if ($st === 'already' || $st === 'duplicate') return ['paid', 'Payment received.'];
        if ($st === 'refund_needed') return ['refund_needed', 'Your payment arrived after the room hold ended and the room is no longer free. The motel will refund you. Reference ' . $b['booking_ref'] . '.'];
        return ['expired', 'Your room hold expired before payment. Please search again.'];
    }
    private function pay_info(array $b): array
    {
        return ['booking' => $this->clean_booking($b), 'wallets' => $this->paymongo->wallets(), 'wallet_names' => \Paymongo::WALLETS,
            'seconds_left' => max(0, strtotime((string) $b['hold_expires_at']) - time())];
    }

    public function pay_info_get($ref)
    {
        $this->need('GET'); $this->mine($ref);
        $this->BookingModel->expire_holds(); $b = $this->mine($ref);
        if ($b['status'] === 'pending_payment' && $this->online()) {
            $s = $this->PaymentModel->settle($b);                                    // maybe it was paid already
            if ($s['result']) { [$state, $msg] = $this->finish($s['result']); $this->ok(['state' => $state, 'message' => $msg] + $this->pay_info($this->mine($ref))); }
        }
        if ($b['status'] !== 'pending_payment') $this->ok(['state' => $b['status'] === 'confirmed' ? 'paid' : $b['status']] + $this->pay_info($b));
        if (!$this->online()) $this->fail('Online payment is not available right now. Please contact the motel.', 503);
        $this->ok(['state' => 'pending'] + $this->pay_info($b));
    }

    public function pay($ref)
    {
        $this->need('POST'); $this->mine($ref);
        $this->BookingModel->expire_holds(); $b = $this->mine($ref);
        $do = (string) ($this->body()['do'] ?? '');
        if (!$this->online()) $this->fail('Online payment is not available right now. Please contact the motel.', 503);
        if ($do === 'check') {                                                      // the pay page asks every few seconds; PayMongo (not the browser) says if it is paid
            $state = $b['status']; $msg = '';
            if ($state === 'pending_payment') {
                $s = $this->PaymentModel->settle($b);
                if ($s['result']) { [$state, $msg] = $this->finish($s['result']); }
                else $state = $s['state'] === 'failed' ? 'failed' : 'pending';
            } elseif ($state === 'confirmed') $state = 'paid';
            $this->ok(['state' => $state, 'message' => $msg]);
        }
        if ($b['status'] !== 'pending_payment') $this->fail('This booking is no longer open for payment.', 409);
        if ($do === 'wallet') {                                                     // GCash / Maya / GrabPay / ShopeePay
            $wallet = (string) ($this->body()['wallet'] ?? '');
            if (!in_array($wallet, $this->paymongo->wallets(), true)) $this->fail('Please choose an e-wallet.', 422);
            $url = $this->PaymentModel->start_wallet($b, $wallet);
        } elseif ($do === 'link') {                                                 // card, online banking, QR
            $url = $this->PaymentModel->start_link($b);
        } else {
            $this->fail('Unknown payment action.', 422);
        }
        if (!$url) {
            if ($this->PaymentModel->error !== '') $this->log('payment_start_failed', $this->PaymentModel->error);       // the owner can read the reason in the Audit log
            $this->fail('Online payment is not available right now. Please try again in a moment or contact the motel.', 502);
        }
        $this->ok(['url' => $url]);
    }

    /* ---------------- extra services (VIP rooms) ---------------- */
    public function request_add($ref)
    {
        $this->need('POST'); $b = $this->mine($ref); $in = $this->body();
        [$ok, $res] = $this->ServiceRequestModel->create($b, trim((string) ($in['service'] ?? '')), trim((string) ($in['note'] ?? '')), 'guest');
        if (!$ok) $this->fail((string) $res, 422);
        $this->log('request_created', $ref . ' · ' . trim((string) $in['service']));
        $this->ok(['message' => 'Your request was sent. The motel team will answer you here and by message.'], 201);
    }

    public function request_pay($ref, $id)          // the guest pays the extra price of an accepted request online (payment link)
    {
        $this->need('POST'); $b = $this->mine($ref); $r = null;
        foreach ($this->ServiceRequestModel->for_booking((int) $b['id']) as $x) if ((int) $x['id'] === (int) $id) $r = $x + ['booking_ref' => $b['booking_ref']];
        if (!$r) $this->fail('Request not found.', 404);
        [$url, $why] = $this->ServiceRequestModel->start_link($r);
        if (!$url) $this->fail($why ?: 'The payment could not be started. Please pay at the desk.', 422);
        $this->ok(['url' => $url]);
    }

    /* ---------------- receipt and calendar file ---------------- */
    public function receipt($ref)
    {
        $this->need('GET'); $b = $this->mine($ref);
        if (!in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'], true)) $this->fail('No receipt for this booking yet.', 404);
        $this->ok(['data' => $this->clean_booking($b), 'payments' => array_values(array_filter($this->PaymentModel->for_booking((int) $b['id']), fn($p) => in_array($p['status'], ['paid', 'refunded'], true))),
            'hotel' => ['name' => hotel_of($b, 'name'), 'address' => hotel_of($b, 'address')]]);
    }

    public function calendar($ref)
    {
        $this->need('GET'); $b = $this->mine($ref);
        if ($b['status'] !== 'confirmed' && $b['status'] !== 'checked_in') $this->fail('Not found.', 404);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="hotelgo-' . $ref . '.ics"');
        echo ics_content($b);
        exit;
    }
}
