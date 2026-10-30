<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Everything here needs a signed-in user (see routes.php). A guest can only ever see HIS OWN bookings. */
class BookingController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model(['RoomTypeModel', 'BookingModel', 'PaymentModel', 'NotificationModel', 'ServiceRequestModel']);
        $this->call->library('paymongo');
    }

    private function ref_ok(string $ref): void { if (!preg_match('/^HG-[A-Z0-9]{6}$/', $ref)) abort(404); }
    private function mine(string $ref): array
    {
        $this->ref_ok($ref);
        $b = $this->BookingModel->find_mine($ref, (int) current_user()['id']);   // user id comes from the SESSION, never from the form
        if (!$b) abort(404, 'We could not find that booking.');
        return $b;
    }

    /* read + check the search values that travel in the web address / hidden fields */
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

    /* ---------------- step 1: details + payment method ---------------- */
    public function checkout_form()
    {
        $sv = $this->search_values($_GET);
        if (!$sv) { flash('bad', 'Please search again – the room or time was not valid.'); go('rooms'); }
        $free = count($this->BookingModel->free_rooms((int) $sv['type']['id'], $sv['w']['start'], $sv['w']['end'], (int) $sv['type']['cleaning_buffer_min']));
        if ($free < 1) { flash('bad', 'Sorry, that room type is fully booked for your time.'); go('rooms?' . http_build_query($sv['p'])); }
        $rate = $this->BookingModel->price($sv['type'], $sv['p']['stay'], (int) $sv['w']['hours']);
        render('booking/checkout', $sv + ['rate' => $rate, 'fee' => (float) setting('booking_fee', '30'), 'user' => current_user(), 'old' => [], 'title' => 'Details & payment']);
    }

    public function checkout()
    {
        $sv = $this->search_values($_POST);       // prices are recalculated here – we never trust a price sent by the browser
        if (!$sv) { flash('bad', 'Your search was not valid. Please search again.'); go('rooms'); }
        $f = ['name' => post_str('name'), 'mobile' => normalize_mobile(post_str('mobile')), 'email' => strtolower(post_str('email')), 'id_type' => post_str('id_type'), 'guests' => $sv['p']['guests']];
        $idNum = preg_replace('/[^A-Za-z0-9]/', '', post_str('id_number'));
        $method = post_str('pay_method'); $err = [];
        if (!len_ok($f['name'], 2, 120)) $err[] = 'Please enter the guest full name.';
        if (!$f['mobile']) $err[] = 'Please enter a valid Philippine mobile number.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Please enter a valid email address.';
        if (!in_array($f['id_type'], ["Driver's license", 'Passport', 'UMID', 'National ID', 'Other government ID'], true)) $err[] = 'Please choose a valid ID type.';
        if (strlen($idNum) < 4 || strlen($idNum) > 30) $err[] = 'Please enter your ID number.';
        if (!in_array($method, ['ewallet', 'card', 'bank'], true)) $err[] = 'Please choose a payment method.';
        if (post_str('agree') !== '1') $err[] = 'Please accept the house rules and cancellation policy.';
        if ($err) {
            foreach ($err as $m) flash('bad', $m);
            render('booking/checkout', $sv + ['rate' => $this->BookingModel->price($sv['type'], $sv['p']['stay'], (int) $sv['w']['hours']), 'fee' => (float) setting('booking_fee', '30'), 'user' => current_user(), 'old' => $_POST, 'title' => 'Details & payment']);
            return;
        }
        $f['id_last4'] = substr($idNum, -4);      // we keep ONLY the last 4 characters of the ID number (data privacy)
        $res = $this->BookingModel->create(current_user(), $sv['type'], $sv['w'], $f, $sv['p']['stay'], 'online', 'pending_payment', $method);
        if (!$res['ok']) { flash('bad', $res['error']); go('rooms?' . http_build_query($sv['p'])); }
        audit('booking_created', $res['ref']);
        go('pay/' . $res['ref']);
    }

    /* ---------------- step 2: payment ---------------- */
    /* The pay page. Mock: sandbox page. PayMongo: choose an e-wallet or a payment link. */
    public function pay($ref)
    {
        $this->mine($ref);
        $this->BookingModel->expire_holds();
        $b = $this->mine($ref);
        if ($b['status'] === 'confirmed') go('booking/' . $ref);
        if ($b['status'] !== 'pending_payment') { flash('bad', 'This booking is no longer open for payment.'); go('my-bookings'); }
        if ($this->paymongo->provider() === 'paymongo') {
            $s = $this->PaymentModel->settle($b);                                    // maybe it was paid already
            if ($s['result']) $this->finish_payment($s['result']);
            render('payment/choose', ['b' => $b, 'wallets' => $this->paymongo->wallets(), 'title' => 'Pay for your room']);
            return;
        }
        if (!$this->PaymentModel->start($b)) { flash('bad', 'Online payment is not available right now. Please contact the motel.'); go('my-bookings'); }
        render('payment/sandbox', ['b' => $b, 'title' => 'Secure payment']);
    }
    private function open_booking(string $ref): array
    {
        $b = $this->mine($ref);
        if ($this->paymongo->provider() !== 'paymongo') abort(404);
        $this->BookingModel->expire_holds();
        $b = $this->mine($ref);
        if ($b['status'] !== 'pending_payment') { flash('bad', 'This booking is no longer open for payment.'); go('booking/' . $ref); }
        return $b;
    }
    private function pay_failed(string $ref): void
    {
        if ($this->PaymentModel->error !== '') audit('payment_start_failed', $this->PaymentModel->error);       // the owner can read the reason in the Audit log
        flash('bad', 'Online payment is not available right now. Please try again in a moment or contact the motel.');
        go('pay/' . $ref);
    }
    public function pay_wallet($ref)            // e-wallet: GCash / Maya / GrabPay / ShopeePay
    {
        $b = $this->open_booking($ref);
        $wallet = post_str('wallet');
        if (!in_array($wallet, $this->paymongo->wallets(), true)) { flash('bad', 'Please choose an e-wallet.'); go('pay/' . $ref); }
        $url = $this->PaymentModel->start_wallet($b, $wallet);
        if (!$url) $this->pay_failed($ref);
        header('Location: ' . $url, true, 302); exit;
    }
    public function pay_link($ref)              // payment link: card, online banking, QR
    {
        $b = $this->open_booking($ref);
        $url = $this->PaymentModel->start_link($b);
        if (!$url) $this->pay_failed($ref);
        header('Location: ' . $url, true, 302); exit;
    }
    public function pay_status($ref)            // the pay page asks this every few seconds: JSON {state}
    {
        $b = $this->mine($ref);
        if ($this->paymongo->provider() !== 'paymongo') abort(404);
        $state = $b['status'];
        if ($state === 'pending_payment') {
            $s = $this->PaymentModel->settle($b);
            if ($s['result']) { $this->after_settle($s['result']); $state = $s['result']['booking']['status'] ?? 'confirmed'; }
        }
        header('Content-Type: application/json'); header('Cache-Control: no-store');
        echo json_encode(['state' => $state === 'confirmed' ? 'paid' : $state]); exit;
    }
    public function pay_confirm($ref)           // MOCK gateway only
    {
        $b = $this->mine($ref);
        if ($this->paymongo->provider() !== 'mock' || !$this->paymongo->mock_allowed()) abort(404);
        $this->finish_payment($this->BookingModel->mark_paid($ref, $b['pay_method'] ?: 'ewallet', 'mock', 'SANDBOX', false));
    }
    public function pay_return($ref)            // the wallet / payment link sends the guest back here. We ask PayMongo (not the browser!) if it is really paid.
    {
        $b = $this->mine($ref);
        if ($this->paymongo->provider() !== 'paymongo') abort(404);
        $s = $this->PaymentModel->settle($b);
        if (!$s['result']) {
            if ($s['state'] === 'failed') { flash('bad', 'The payment was not completed. You can try again while your room is held.'); go('pay/' . $ref); }
            flash('warn', 'We have not received your payment yet. If you already paid, it appears here in a moment.'); go('pay/' . $ref);
        }
        $this->finish_payment($s['result']);
    }
    private function after_settle(array $r): void
    {
        if (in_array($r['status'], ['confirmed', 'confirmed_late'], true)) {
            audit('payment_paid', $r['booking']['booking_ref']);
            $this->NotificationModel->booking_confirmed($r['booking']);
        }
    }
    private function finish_payment(array $r): void
    {
        $b = $r['booking']; $st = $r['status'];
        if ($st === 'confirmed' || $st === 'confirmed_late') {
            audit('payment_paid', $b['booking_ref']);
            $this->NotificationModel->booking_confirmed($b);
            flash('ok', 'Payment received. Your room has been assigned!'); go('booking/' . $b['booking_ref']);
        }
        if ($st === 'already' || $st === 'duplicate') go('booking/' . $b['booking_ref']);
        if ($st === 'refund_needed') { flash('bad', 'Your payment arrived after the room hold ended and the room is no longer free. The motel will refund you. Reference ' . $b['booking_ref'] . '.'); go('my-bookings'); }
        flash('bad', 'Your room hold expired before payment. Please search again.'); go('rooms');
    }

    /* ---------------- my bookings ---------------- */
    public function mine_list()
    {
        $this->BookingModel->expire_holds();
        $tab = in_array(get_str('tab', 'upcoming'), ['upcoming', 'past', 'cancelled'], true) ? get_str('tab', 'upcoming') : 'upcoming';
        render('booking/mine', ['tab' => $tab, 'rows' => $this->BookingModel->mine((int) current_user()['id'], $tab), 'title' => 'My bookings']);
    }
    public function show($ref)
    {
        $b = $this->mine($ref);
        $latest = fn(string $ch) => $this->NotificationModel->latest((int) $b['id'], $ch);
        $reqs = $this->ServiceRequestModel->for_booking((int) $b['id']);
        $this->ServiceRequestModel->settle_many(array_map(fn($r) => $r + ['booking_ref' => $b['booking_ref']], $reqs));        // a guest who just paid an extra link is shown as paid
        $reqs = $this->ServiceRequestModel->for_booking((int) $b['id']);
        render('booking/show', ['b' => $b, 'can_cancel' => $this->BookingModel->can_cancel($b), 'can_modify' => $b['status'] === 'confirmed' && $this->BookingModel->can_cancel($b),
            'sms' => $latest('sms'), 'mail' => $latest('email'), 'requests' => $reqs, 'services' => $this->ServiceRequestModel->services(),
            'online' => $this->paymongo->provider() === 'paymongo', 'title' => 'Booking ' . $ref]);
    }
    /* ---------------- extra services (VIP rooms) ---------------- */
    public function request_service($ref)
    {
        $b = $this->mine($ref);
        [$ok, $res] = $this->ServiceRequestModel->create($b, post_str('service'), post_str('note'), 'guest');
        if ($ok) { audit('request_created', $ref . ' · ' . post_str('service')); flash('ok', 'Your request was sent. The motel team will answer you here and by message.'); }
        else flash('bad', (string) $res);
        go('booking/' . $ref);
    }
    public function request_pay($ref, $id)          // the guest pays the extra price of an accepted request online (payment link)
    {
        $b = $this->mine($ref);
        $r = null;
        foreach ($this->ServiceRequestModel->for_booking((int) $b['id']) as $x) if ((int) $x['id'] === (int) $id) $r = $x + ['booking_ref' => $b['booking_ref']];
        if (!$r) abort(404);
        [$url, $why] = $this->ServiceRequestModel->start_link($r);
        if (!$url) { flash('bad', $why ?: 'The payment could not be started. Please pay at the desk.'); go('booking/' . $ref); }
        header('Location: ' . $url, true, 302); exit;
    }
    public function receipt($ref)
    {
        $b = $this->mine($ref);
        if (!in_array($b['status'], ['confirmed', 'checked_in', 'checked_out'], true)) abort(404);
        render('booking/receipt', ['b' => $b, 'pay' => $this->PaymentModel->receipt_for((int) $b['id']), 'title' => 'Receipt ' . $ref]);
    }
    private function modifiable(string $ref): array
    {
        $b = $this->mine($ref);
        if ($b['status'] !== 'confirmed' || !$this->BookingModel->can_cancel($b)) { flash('bad', 'This booking can no longer be changed online. Please contact the motel.'); go('booking/' . $ref); }
        return $b;
    }
    public function modify_form($ref) { render('booking/modify', ['b' => $this->modifiable($ref), 'title' => 'Change booking']); }
    public function modify($ref)
    {
        $b = $this->modifiable($ref);
        $w = $this->BookingModel->window($b['stay_type'], post_str('date'), post_str('time', date('H:i', strtotime($b['start_at']))), (int) $b['hours']);
        if (!$w) { flash('bad', 'Please choose a valid date and time (not in the past, within 60 days).'); go('booking/' . $ref . '/modify'); }
        $res = $this->BookingModel->reschedule($b, $w);
        if (!$res['ok']) { flash('bad', $res['error']); go('booking/' . $ref . '/modify'); }
        audit('booking_rescheduled', $ref);
        $this->NotificationModel->booking_rescheduled($this->BookingModel->by_ref($ref));
        flash('ok', 'Your booking was moved to the new time.');
        go('booking/' . $ref);
    }
    public function cancel($ref)
    {
        $b = $this->mine($ref);
        if (!$this->BookingModel->can_cancel($b)) { flash('bad', 'This booking can no longer be cancelled online. Please contact the motel.'); go('booking/' . $ref); }
        $this->BookingModel->cancel($b);
        $refunded = $this->PaymentModel->refund_booking($b);
        audit('booking_cancelled', $ref);
        if ($b['status'] === 'confirmed') $this->NotificationModel->booking_cancelled($b);
        flash($refunded ? 'ok' : 'warn', $refunded ? 'Booking ' . $ref . ' was cancelled.' : 'Booking ' . $ref . ' was cancelled. Your refund is being processed by the motel.');
        go('my-bookings');
    }
    public function ics($ref)
    {
        $b = $this->mine($ref);
        if ($b['status'] !== 'confirmed' && $b['status'] !== 'checked_in') abort(404);
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="hotelgo-' . $ref . '.ics"');
        echo ics_content($b);
        exit;
    }
}
