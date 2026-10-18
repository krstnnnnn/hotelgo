<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Everything here needs a signed-in user (see routes.php). A guest can only ever see HIS OWN bookings. */
class BookingController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        foreach (['RoomModel', 'BookingModel', 'PaymentModel', 'NotificationModel'] as $m) $this->call->model($m);
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
        $type = $typeId ? $this->RoomModel->type($typeId) : null;
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
    public function pay($ref)
    {
        $b = $this->mine($ref);
        $this->BookingModel->expire_holds();
        $b = $this->mine($ref);
        if ($b['status'] === 'confirmed') go('booking/' . $ref);
        if ($b['status'] !== 'pending_payment') { flash('bad', 'This booking is no longer open for payment.'); go('my-bookings'); }
        $url = $this->PaymentModel->start($b);
        if (!$url) { flash('bad', 'Online payment is not available right now. Please contact the motel.'); go('my-bookings'); }
        if ($this->PaymentModel->provider() === 'paymongo') { header('Location: ' . $url, true, 302); exit; }
        render('payment/sandbox', ['b' => $b, 'title' => 'Secure payment']);
    }
    public function pay_confirm($ref)           // MOCK gateway only
    {
        $b = $this->mine($ref);
        if ($this->PaymentModel->provider() !== 'mock' || !$this->PaymentModel->mock_allowed()) abort(404);
        $paid = $this->BookingModel->mark_paid($ref, $b['pay_method'] ?: 'ewallet', 'mock', 'SANDBOX');
        if (!$paid) { flash('bad', 'Your room hold expired before payment. Please search again.'); go('rooms'); }
        $this->after_paid($paid);
    }
    public function pay_return($ref)            // PayMongo comes back here; we ask PayMongo if it is really paid
    {
        $b = $this->mine($ref);
        if ($this->PaymentModel->provider() !== 'paymongo' || !$this->PaymentModel->paymongo_paid($b)) { flash('bad', 'We could not confirm your payment yet.'); go('my-bookings'); }
        $paid = $this->BookingModel->mark_paid($ref, $b['pay_method'] ?: 'ewallet', 'paymongo', $b['pay_ref']);
        if (!$paid) { flash('bad', 'This booking could not be confirmed. Please contact the motel with your reference ' . $ref . '.'); go('my-bookings'); }
        $this->after_paid($paid);
    }
    private function after_paid(array $b): void
    {
        audit('payment_paid', $b['booking_ref']);
        $this->NotificationModel->booking_confirmed($b);
        flash('ok', 'Payment received. Your room has been assigned!');
        go('booking/' . $b['booking_ref']);
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
        render('booking/show', ['b' => $b, 'can_cancel' => $this->BookingModel->can_cancel($b), 'title' => 'Booking ' . $ref]);
    }
    public function cancel($ref)
    {
        $b = $this->mine($ref);
        if (!$this->BookingModel->can_cancel($b)) { flash('bad', 'This booking can no longer be cancelled online. Please contact the motel.'); go('booking/' . $ref); }
        $this->BookingModel->cancel($b);
        audit('booking_cancelled', $ref);
        if ($b['status'] === 'confirmed') $this->NotificationModel->booking_cancelled($b);
        flash('ok', 'Booking ' . $ref . ' was cancelled.');
        go('my-bookings');
    }
    public function ics($ref)
    {
        $b = $this->mine($ref);
        if ($b['status'] !== 'confirmed' && $b['status'] !== 'checked_in') abort(404);
        $esc = fn($s) => preg_replace('/([,;\\\\])/', '\\\\$1', str_replace(["\r", "\n"], ' ', (string) $s));
        $utc = fn($s) => gmdate('Ymd\THis\Z', strtotime($s));
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="hotelgo-' . $ref . '.ics"');
        echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HOTELGO//EN\r\nBEGIN:VEVENT\r\nUID:" . $ref . "@hotelgo\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . $utc($b['start_at']) . "\r\nDTEND:" . $utc($b['end_at']) . "\r\n";
        echo 'SUMMARY:' . $esc('HOTELGO · Room ' . $b['room_no']) . "\r\nLOCATION:" . $esc(setting('motel_name') . ', ' . setting('motel_address')) . "\r\nBEGIN:VALARM\r\nTRIGGER:-PT30M\r\nACTION:DISPLAY\r\nDESCRIPTION:Check-in in 30 minutes\r\nEND:VALARM\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
        exit;
    }
}
